<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreOutgoingParcelRequest;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Services\OutgoingParcelService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutgoingParcelController extends Controller
{
    private const SELECT_COLUMNS = [
        'id',
        'office_id',
        'shipment_id',
        'destination_office_id',
        'external_office_name',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
        'cod_amount',
        'payment_type',
        'notes',
        'status',
        'linked_incoming_parcel_id',
        'dispatched_at',
        'received_at',
        'cancelled_at',
        'created_at',
    ];

    private const EAGER_RELATIONS = [
        'destinationOffice:id,name,branch_name',
        'linkedIncomingParcel:id,status,delivered_at',
        'shipment:id,shipment_number,status',
    ];

    public function index(Request $request)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $statusFilter = (string) $request->query('status', 'all');
        $rawSearch = trim((string) $request->query('q', ''));
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $search = str_replace($eastern, $western, $rawSearch);

        $query = OutgoingParcel::query()
            ->select(self::SELECT_COLUMNS)
            ->where('office_id', $office->id)
            ->with(self::EAGER_RELATIONS);

        $allowedStatuses = array_map(
            static fn (OutgoingParcelStatus $s) => $s->value,
            OutgoingParcelStatus::cases()
        );

        if (in_array($statusFilter, $allowedStatuses, true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('recipient_phone', 'like', $like)
                    ->orWhere('receipt_number', 'like', $like)
                    ->orWhere('external_office_name', 'like', $like);
            });
        }

        $counts = $this->getStatusCounts($office);
        $parcels = $query->latest('created_at')->paginate(20)->withQueryString();

        // استجابة JSON خفيفة للبحث والتصفية الفورية بدون إعادة تحميل الصفحة أو استعلام الفروع مجدداً
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'            => true,
                'status_filter' => $statusFilter,
                'search'        => $search,
                'counts'        => $counts,
                'html'          => view('office.outgoing_parcels.partials.cards-list', compact('parcels'))->render(),
            ]);
        }

        $destinationOffices = $office->partnerOffices()
            ->where('offices.is_active', true)
            ->orderBy('offices.name')
            ->orderBy('offices.branch_name')
            ->get(['offices.id', 'offices.name', 'offices.branch_name']);

        return view('office.outgoing_parcels.index', compact(
            'office',
            'parcels',
            'counts',
            'destinationOffices',
            'statusFilter',
            'search'
        ));
    }

    public function store(StoreOutgoingParcelRequest $request, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $validated = $request->validated();

        if (($validated['destination_type'] ?? 'office') === 'office') {
            $validated['external_office_name'] = null;
        } else {
            $validated['destination_office_id'] = null;
        }

        try {
            $created = $service->create($office, $validated);

            if ($request->expectsJson() || $request->ajax()) {
                $created->load(self::EAGER_RELATIONS);

                return response()->json([
                    'ok'        => true,
                    'message'   => 'تم تسجيل الطرد الصادر بنجاح.',
                    'counts'    => $this->getStatusCounts($office),
                    'parcel'    => $this->formatParcelJson($created),
                    'card_html' => view('office.outgoing_parcels.partials.parcel-card', ['parcel' => $created])->render(),
                ], 201);
            }

            return redirect()
                ->route('office.outgoing-parcels.index')
                ->with('success', 'تم تسجيل الطرد الصادر بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function dispatch(Request $request, OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $updated = $service->markInTransit($outgoingParcel, $office);

            if ($request->expectsJson() || $request->ajax()) {
                $updated->load(self::EAGER_RELATIONS);

                return response()->json([
                    'ok'        => true,
                    'message'   => 'تم تحديث حالة الطرد إلى (في الطريق) بنجاح.',
                    'counts'    => $this->getStatusCounts($office),
                    'parcel'    => $this->formatParcelJson($updated),
                    'card_html' => view('office.outgoing_parcels.partials.parcel-card', ['parcel' => $updated])->render(),
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'تم تحديث حالة الطرد إلى (في الطريق) بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function deliverExternal(Request $request, OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $updated = $service->markExternalDelivered($outgoingParcel, $office);

            if ($request->expectsJson() || $request->ajax()) {
                $updated->load(self::EAGER_RELATIONS);

                return response()->json([
                    'ok'        => true,
                    'message'   => 'تم تأكيد تسليم الطرد للمكتب الخارجي بنجاح.',
                    'counts'    => $this->getStatusCounts($office),
                    'parcel'    => $this->formatParcelJson($updated),
                    'card_html' => view('office.outgoing_parcels.partials.parcel-card', ['parcel' => $updated])->render(),
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'تم تأكيد تسليم الطرد للمكتب الخارجي بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $updated = $service->cancel($outgoingParcel, $office);

            if ($request->expectsJson() || $request->ajax()) {
                $updated->load(self::EAGER_RELATIONS);

                return response()->json([
                    'ok'        => true,
                    'message'   => 'تم إلغاء الطرد الصادر بنجاح.',
                    'counts'    => $this->getStatusCounts($office),
                    'parcel'    => $this->formatParcelJson($updated),
                    'card_html' => view('office.outgoing_parcels.partials.parcel-card', ['parcel' => $updated])->render(),
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'تم إلغاء الطرد الصادر بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * استعلام موحد واحد لحساب عدادات الحالات للمكتب.
     *
     * @return array{all:int, pending:int, in_transit:int, received:int, delivered:int, cancelled:int}
     */
    private function getStatusCounts(Office $office): array
    {
        $rawCounts = OutgoingParcel::where('office_id', $office->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all'        => (int) $rawCounts->sum(),
            'pending'    => (int) ($rawCounts[OutgoingParcelStatus::PENDING->value] ?? 0),
            'in_transit' => (int) ($rawCounts[OutgoingParcelStatus::IN_TRANSIT->value] ?? 0),
            'received'   => (int) ($rawCounts[OutgoingParcelStatus::RECEIVED->value] ?? 0),
            'delivered'  => (int) ($rawCounts[OutgoingParcelStatus::DELIVERED->value] ?? 0),
            'cancelled'  => (int) ($rawCounts[OutgoingParcelStatus::CANCELLED->value] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatParcelJson(OutgoingParcel $parcel): array
    {
        return [
            'id'           => $parcel->id,
            'status'       => $parcel->status->value,
            'status_label' => $parcel->status->label(),
            'badge_class'  => $parcel->status->badgeClass(),
        ];
    }
}
