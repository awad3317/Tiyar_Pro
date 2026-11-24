<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OutgoingParcelStatus;
use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParcelController extends Controller
{
    /**
     * مراقبة الطرود الشاملة لجميع المكاتب (المستلمة والمرسلة) مع نظام فلترة متقدم
     */
    public function index(Request $request): View
    {
        $rawType = strtolower(trim((string) $request->input('type', 'all')));
        $activeType = in_array($rawType, ['all', 'incoming', 'outgoing'], true) ? $rawType : 'all';

        $incomingBase = Parcel::query();
        $outgoingBase = OutgoingParcel::query();

        $this->applyCommonFilters($incomingBase, $outgoingBase, $request);

        // عداد الطرود التي فشل إرسال SMS لها اليوم فقط (محسوب دائماً لليوم الحالي مع احترام فلتر المكتب إن وُجد)
        $todayFailedSmsCount = Parcel::query()
            ->when($request->filled('office_id'), fn (Builder $q) => $q->where('office_id', $request->input('office_id')))
            ->whereDate('created_at', today())
            ->whereIn('sms_status', ['failed', 'failed_balance'])
            ->count();

        // فلتر اختياري لعرض الطرود الفاشلة في إرسال SMS فقط (عند الضغط على كارد فشل SMS اليوم أو من الفلاتر)
        $onlySmsFailed = $request->boolean('sms_failed');
        if ($onlySmsFailed) {
            $incomingBase->whereIn('sms_status', ['failed', 'failed_balance']);
        }

        // عدادات التبويبات الثلاثة بناءً على فلاتر المكتب والتاريخ والبحث (قبل تضييق النوع أو الحالة)
        $incomingCountBeforeStatus = (clone $incomingBase)->count();
        $outgoingCountBeforeStatus = $onlySmsFailed ? 0 : (clone $outgoingBase)->count();
        $tabCounts = [
            'all'      => $incomingCountBeforeStatus + $outgoingCountBeforeStatus,
            'incoming' => $incomingCountBeforeStatus,
            'outgoing' => $outgoingCountBeforeStatus,
        ];

        // تطبيق فلتر الحالة الذكي (يدعم incoming:... و outgoing:... والحالات المباشرة)
        $statusFilter = trim((string) $request->input('status', ''));
        [$includeIncoming, $includeOutgoing] = $this->applyStatusFilter(
            $incomingBase,
            $outgoingBase,
            $activeType,
            $statusFilter
        );
        if ($onlySmsFailed) {
            $includeOutgoing = false;
        }

        // إحصائيات سريعة للنتائج المفلترة
        $incomingFilteredCount = $includeIncoming ? (clone $incomingBase)->count() : 0;
        $inOfficeCount = $includeIncoming ? (clone $incomingBase)->where('status', ParcelStatus::InOffice)->count() : 0;
        $incomingDeliveredCount = $includeIncoming ? (clone $incomingBase)->where('status', ParcelStatus::Delivered)->count() : 0;
        $returnedCount = $includeIncoming ? (clone $incomingBase)->where('status', ParcelStatus::Returned)->count() : 0;

        $outgoingFilteredCount = $includeOutgoing ? (clone $outgoingBase)->count() : 0;
        $outgoingPendingCount = $includeOutgoing ? (clone $outgoingBase)->where('status', OutgoingParcelStatus::PENDING)->count() : 0;
        $outgoingInTransitCount = $includeOutgoing ? (clone $outgoingBase)->where('status', OutgoingParcelStatus::IN_TRANSIT)->count() : 0;
        $outgoingCompletedCount = $includeOutgoing ? (clone $outgoingBase)->whereIn('status', [
            OutgoingParcelStatus::RECEIVED,
            OutgoingParcelStatus::DELIVERED,
        ])->count() : 0;
        $outgoingCancelledCount = $includeOutgoing ? (clone $outgoingBase)->where('status', OutgoingParcelStatus::CANCELLED)->count() : 0;

        $filteredStats = [
            'total'               => $incomingFilteredCount + $outgoingFilteredCount,
            'incoming_total'      => $incomingFilteredCount,
            'in_office'           => $inOfficeCount,
            'delivered'           => $incomingDeliveredCount,
            'returned'            => $returnedCount,
            'outgoing_total'      => $outgoingFilteredCount,
            'outgoing_pending'    => $outgoingPendingCount,
            'outgoing_in_transit' => $outgoingInTransitCount,
            'outgoing_completed'  => $outgoingCompletedCount,
            'outgoing_cancelled'  => $outgoingCancelledCount,
            'today_failed_sms'    => $todayFailedSmsCount,
        ];

        $parcels = $this->paginateParcels(
            $incomingBase,
            $outgoingBase,
            $activeType,
            $includeIncoming,
            $includeOutgoing,
            $request
        );

        $offices = Office::select('id', 'name', 'branch_name')->orderBy('name')->get();

        return view('admin.parcels.index', [
            'parcels'             => $parcels,
            'offices'             => $offices,
            'activeType'          => $activeType,
            'tabCounts'           => $tabCounts,
            'filteredStats'       => $filteredStats,
            'todayFailedSmsCount' => $todayFailedSmsCount,
            'statuses'            => ParcelStatus::cases(),
            'outgoingStatuses'    => OutgoingParcelStatus::cases(),
            'filters'             => array_merge(
                ['type' => $activeType],
                $request->only(['office_id', 'status', 'date_from', 'date_to', 'date_preset', 'search', 'sms_failed'])
            ),
        ]);
    }

    /**
     * تحديث حالة طرد مستلم (وارد)
     */
    public function updateStatus(Request $request, Parcel $parcel): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ParcelStatus::class)],
        ]);

        $newStatus = ParcelStatus::from($validated['status']);
        $previousStatus = $parcel->status;

        $parcel->status = $newStatus;
        if ($newStatus === ParcelStatus::Delivered) {
            $parcel->delivered_at = $parcel->delivered_at ?: now();
        } else {
            $parcel->delivered_at = null;
        }
        $parcel->save();

        if ($newStatus === ParcelStatus::Delivered && $previousStatus !== ParcelStatus::Delivered) {
            dispatch(function () use ($parcel) {
                app(\App\Services\DeliveryNotificationService::class)->sendDeliveryNotification($parcel);
            })->afterResponse();
        }

        $msg = "تم تغيير حالة الطرد المستلم ({$parcel->receipt_number}) إلى \"{$newStatus->label()}\" بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'direction' => 'incoming',
                'message'   => $msg,
                'status'    => $newStatus->value,
                'label'     => $newStatus->label(),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * حذف طرد مستلم (وارد) نهائياً
     */
    public function destroy(Request $request, Parcel $parcel): JsonResponse|RedirectResponse
    {
        $receipt = $parcel->receipt_number ?: $parcel->id;
        $parcel->delete();

        $msg = "تم حذف الطرد المستلم رقم ({$receipt}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * تحديث حالة طرد مرسل (صادر) من قِبل الإدارة
     */
    public function updateOutgoingStatus(Request $request, OutgoingParcel $outgoingParcel): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OutgoingParcelStatus::class)],
        ]);

        $newStatus = OutgoingParcelStatus::from($validated['status']);
        $outgoingParcel->status = $newStatus;

        if ($newStatus === OutgoingParcelStatus::IN_TRANSIT) {
            $outgoingParcel->dispatched_at = $outgoingParcel->dispatched_at ?: now();
            $outgoingParcel->cancelled_at = null;
        } elseif (in_array($newStatus, [OutgoingParcelStatus::RECEIVED, OutgoingParcelStatus::DELIVERED], true)) {
            $outgoingParcel->dispatched_at = $outgoingParcel->dispatched_at ?: now();
            $outgoingParcel->received_at = $outgoingParcel->received_at ?: now();
            $outgoingParcel->cancelled_at = null;
        } elseif ($newStatus === OutgoingParcelStatus::CANCELLED) {
            $outgoingParcel->cancelled_at = $outgoingParcel->cancelled_at ?: now();
        } elseif ($newStatus === OutgoingParcelStatus::PENDING) {
            $outgoingParcel->received_at = null;
            $outgoingParcel->cancelled_at = null;
        }

        $outgoingParcel->save();

        $msg = "تم تغيير حالة الطرد المرسل ({$outgoingParcel->receipt_number}) إلى \"{$newStatus->label()}\" بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'direction' => 'outgoing',
                'message'   => $msg,
                'status'    => $newStatus->value,
                'label'     => $newStatus->label(),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * حذف طرد مرسل (صادر) نهائياً من قِبل الإدارة
     */
    public function destroyOutgoing(Request $request, OutgoingParcel $outgoingParcel): JsonResponse|RedirectResponse
    {
        $receipt = $outgoingParcel->receipt_number ?: $outgoingParcel->id;
        $outgoingParcel->delete();

        $msg = "تم حذف الطرد المرسل رقم ({$receipt}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * تطبيق فلاتر المكتب والتاريخ والبحث الموحد على جدولي المستلم والمرسل
     */
    protected function applyCommonFilters(Builder $incomingQuery, Builder $outgoingQuery, Request $request): void
    {
        if ($officeId = $request->input('office_id')) {
            $incomingQuery->where('office_id', $officeId);
            $outgoingQuery->where('office_id', $officeId);
        }

        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from');
            $incomingQuery->whereDate('created_at', '>=', $dateFrom);
            $outgoingQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to');
            $incomingQuery->whereDate('created_at', '<=', $dateTo);
            $outgoingQuery->whereDate('created_at', '<=', $dateTo);
        }

        if ($preset = $request->input('date_preset')) {
            foreach ([$incomingQuery, $outgoingQuery] as $q) {
                match ($preset) {
                    'today'      => $q->whereDate('created_at', today()),
                    'yesterday'  => $q->whereDate('created_at', today()->subDay()),
                    'this_week'  => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'this_month' => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                    default      => null,
                };
            }
        }

        $rawSearch = trim((string) $request->input('search', ''));
        if ($rawSearch !== '') {
            $normalized = strtr($rawSearch, [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);
            $cleanPhone = preg_replace('/[^0-9]/', '', $normalized) ?? '';
            if (str_starts_with($cleanPhone, '00967') && strlen($cleanPhone) > 5) {
                $cleanPhone = substr($cleanPhone, 5);
            } elseif (str_starts_with($cleanPhone, '967') && strlen($cleanPhone) > 9) {
                $cleanPhone = substr($cleanPhone, 3);
            }

            $incomingQuery->where(function (Builder $q) use ($normalized, $cleanPhone) {
                $q->where('recipient_name', 'like', "%{$normalized}%")
                    ->orWhere('receipt_number', 'like', "%{$normalized}%")
                    ->orWhere('slot_number', 'like', "%{$normalized}%")
                    ->orWhere('package_type', 'like', "%{$normalized}%")
                    ->orWhere('source_region', 'like', "%{$normalized}%")
                    ->orWhere('source_office_name', 'like', "%{$normalized}%");

                if ($cleanPhone !== '') {
                    $q->orWhere('recipient_phone', 'like', "%{$cleanPhone}%");
                }
            });

            $outgoingQuery->where(function (Builder $q) use ($normalized, $cleanPhone) {
                $q->where('recipient_name', 'like', "%{$normalized}%")
                    ->orWhere('sender_name', 'like', "%{$normalized}%")
                    ->orWhere('receipt_number', 'like', "%{$normalized}%")
                    ->orWhere('package_type', 'like', "%{$normalized}%")
                    ->orWhere('external_office_name', 'like', "%{$normalized}%")
                    ->orWhere('notes', 'like', "%{$normalized}%")
                    ->orWhereHas('destinationOffice', function (Builder $dq) use ($normalized) {
                        $dq->where('name', 'like', "%{$normalized}%")
                            ->orWhere('branch_name', 'like', "%{$normalized}%");
                    });

                if ($cleanPhone !== '') {
                    $q->orWhere('recipient_phone', 'like', "%{$cleanPhone}%")
                        ->orWhere('sender_phone', 'like', "%{$cleanPhone}%");
                }
            });
        }
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    protected function applyStatusFilter(
        Builder $incomingQuery,
        Builder $outgoingQuery,
        string $activeType,
        string $statusFilter
    ): array {
        $includeIncoming = $activeType !== 'outgoing';
        $includeOutgoing = $activeType !== 'incoming';

        if ($statusFilter === '') {
            return [$includeIncoming, $includeOutgoing];
        }

        if (str_starts_with($statusFilter, 'incoming:')) {
            $val = substr($statusFilter, strlen('incoming:'));
            $incomingQuery->where('status', $val);

            return [$includeIncoming, false];
        }

        if (str_starts_with($statusFilter, 'outgoing:')) {
            $val = substr($statusFilter, strlen('outgoing:'));
            $outgoingQuery->where('status', $val);

            return [false, $includeOutgoing];
        }

        $isIncomingStatus = ParcelStatus::tryFrom($statusFilter) !== null;
        $isOutgoingStatus = OutgoingParcelStatus::tryFrom($statusFilter) !== null;

        if ($isIncomingStatus) {
            $incomingQuery->where('status', $statusFilter);
        } else {
            $includeIncoming = false;
        }

        if ($isOutgoingStatus) {
            $outgoingQuery->where('status', $statusFilter);
        } else {
            $includeOutgoing = false;
        }

        return [$includeIncoming, $includeOutgoing];
    }

    protected function paginateParcels(
        Builder $incomingQuery,
        Builder $outgoingQuery,
        string $activeType,
        bool $includeIncoming,
        bool $includeOutgoing,
        Request $request
    ): LengthAwarePaginator {
        if ($activeType === 'incoming' || ($includeIncoming && ! $includeOutgoing)) {
            if (! $includeIncoming) {
                return new LengthAwarePaginator(collect(), 0, 20, 1, [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]);
            }

            $paginator = $incomingQuery->with('office')->latest('created_at')->paginate(20)->withQueryString();
            $paginator->getCollection()->each(function (Parcel $p): void {
                $p->setAttribute('parcel_direction', 'incoming');
            });

            return $paginator;
        }

        if ($activeType === 'outgoing' || ($includeOutgoing && ! $includeIncoming)) {
            if (! $includeOutgoing) {
                return new LengthAwarePaginator(collect(), 0, 20, 1, [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]);
            }

            $paginator = $outgoingQuery
                ->with(['office', 'externalPartnerLink', 'destinationOffice', 'shipment:id,shipment_number'])
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString();
            $paginator->getCollection()->each(function (OutgoingParcel $p): void {
                $p->setAttribute('parcel_direction', 'outgoing');
            });

            return $paginator;
        }

        if (! $includeIncoming && ! $includeOutgoing) {
            return new LengthAwarePaginator(collect(), 0, 20, 1, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);
        }

        // دمج الجدولين بترقيم صفحات مباشر على مستوى قاعدة البيانات دون استهلاك الذاكرة
        $inSub = (clone $incomingQuery)->selectRaw(
            "CAST(id AS CHAR(36)) COLLATE utf8mb4_unicode_ci as id, CAST('incoming' AS CHAR(16)) COLLATE utf8mb4_unicode_ci as parcel_direction, created_at"
        );
        $outSub = (clone $outgoingQuery)->selectRaw(
            "CAST(id AS CHAR(36)) COLLATE utf8mb4_unicode_ci as id, CAST('outgoing' AS CHAR(16)) COLLATE utf8mb4_unicode_ci as parcel_direction, created_at"
        );

        $rawPaginator = DB::query()
            ->fromSub($inSub->unionAll($outSub), 'combined_parcels')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $rows = collect($rawPaginator->items());
        $incomingIds = $rows->where('parcel_direction', 'incoming')->pluck('id')->all();
        $outgoingIds = $rows->where('parcel_direction', 'outgoing')->pluck('id')->all();

        $incomingMap = ! empty($incomingIds)
            ? Parcel::with('office')->whereIn('id', $incomingIds)->get()->keyBy('id')
            : collect();

        $outgoingMap = ! empty($outgoingIds)
            ? OutgoingParcel::with(['office', 'externalPartnerLink', 'destinationOffice', 'shipment:id,shipment_number'])
                ->whereIn('id', $outgoingIds)
                ->get()
                ->keyBy('id')
            : collect();

        $hydrated = $rows->map(function (object $row) use ($incomingMap, $outgoingMap) {
            if ($row->parcel_direction === 'outgoing') {
                $model = $outgoingMap->get($row->id);
                if ($model) {
                    $model->setAttribute('parcel_direction', 'outgoing');
                }

                return $model;
            }

            $model = $incomingMap->get($row->id);
            if ($model) {
                $model->setAttribute('parcel_direction', 'incoming');
            }

            return $model;
        })->filter()->values();

        return $rawPaginator->setCollection($hydrated);
    }
}
