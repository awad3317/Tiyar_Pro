<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    /**
     * شاشة الشحنات المرسلة (الصادرة) للمكتب + تجهيز الطرود المتاحة للضم.
     */
    public function sentIndex(Request $request): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $baseQuery = Shipment::query()->where('office_id', $office->id);

        $counts = [
            'all'                => (clone $baseQuery)->count(),
            'draft'              => (clone $baseQuery)->where('status', ShipmentStatus::DRAFT->value)->count(),
            'in_transit'         => (clone $baseQuery)->where('status', ShipmentStatus::IN_TRANSIT->value)->count(),
            'partially_received' => (clone $baseQuery)->where('status', ShipmentStatus::PARTIALLY_RECEIVED->value)->count(),
            'received'           => (clone $baseQuery)->where('status', ShipmentStatus::RECEIVED->value)->count(),
        ];

        $query = Shipment::withCount([
            'parcels as received_parcels_count' => fn($q) => $q->whereIn('status', [
                OutgoingParcelStatus::RECEIVED->value,
                OutgoingParcelStatus::DELIVERED->value,
            ]),
        ])->where('office_id', $office->id);

        if ($statusFilter !== 'all' && ShipmentStatus::tryFrom($statusFilter) !== null) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('driver_phone', 'like', "%{$search}%");
            });
        }

        $shipments = $query->latest()->paginate(20)->withQueryString();

        // الطرود الصادرة الجاهزة (pending وغير مضمومة لشحنة) لضمها بالمسح أو الاختيار اليدوي
        $availableParcels = OutgoingParcel::with('destinationOffice:id,name,branch_name')
            ->where('office_id', $office->id)
            ->where('status', OutgoingParcelStatus::PENDING->value)
            ->whereNull('shipment_id')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn(OutgoingParcel $p) => $this->serializeParcelForShipmentJs($p))
            ->values();

        return view('office.shipments.sent', compact(
            'office',
            'shipments',
            'counts',
            'statusFilter',
            'search',
            'availableParcels'
        ));
    }

    /**
     * فحص باركود طرد صادر (UUID أو رقم سند) أثناء إنشاء شحنة جديدة.
     */
    public function lookupParcel(Request $request): JsonResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $code = (string) $request->input('code', '');

        try {
            $parcel = $this->shipmentService->findParcelForSendingScan($office, $code);

            return response()->json([
                'ok'     => true,
                'parcel' => $this->serializeParcelForShipmentJs($parcel),
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * إنشاء كشف شحنة جديدة من الطرود الممسوحة/المختارة.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $validated = $request->validate([
            'driver_name'  => ['required', 'string', 'max:120'],
            'driver_phone' => ['required', 'string', 'max:25'],
            'parcel_ids'   => ['required', 'array', 'min:1', 'max:300'],
            'parcel_ids.*' => ['required', 'uuid'],
            'dispatch_now' => ['nullable', 'boolean'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ], [
            'driver_name.required'  => 'يرجى إدخال اسم السائق.',
            'driver_phone.required' => 'يرجى إدخال رقم جوال السائق.',
            'parcel_ids.required'   => 'يرجى مسح أو تحديد طرد واحد على الأقل.',
            'parcel_ids.min'        => 'يرجى مسح أو تحديد طرد واحد على الأقل.',
        ]);

        try {
            $shipment = $this->shipmentService->create($office, $validated);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'           => true,
                    'message'      => "تم إنشاء كشف الشحنة رقم #{$shipment->shipment_number} بنجاح.",
                    'shipment'     => $shipment,
                    'redirect_url' => route('office.shipments.sent.show', $shipment),
                ], 201);
            }

            return redirect()
                ->route('office.shipments.sent.show', $shipment)
                ->with('success', "تم إنشاء كشف الشحنة رقم #{$shipment->shipment_number} بنجاح.");
        } catch (DomainException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * تفاصيل كشف الشحنة المرسلة.
     */
    public function showSent(Shipment $shipment): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $shipment->office_id === (int) $office->id, 403);

        $shipment->load([
            'office:id,name,branch_name',
            'parcels' => fn ($q) => $q->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')->orderBy('created_at'),
            'parcels.destinationOffice:id,name,branch_name',
            'parcels.linkedIncomingParcel:id,status',
        ]);

        return view('office.shipments.sent-show', compact('office', 'shipment'));
    }

    /**
     * صفحة طباعة كشف الشحنة المرسلة الرسمية (A4 مطابقة لتصميم كشف المحاسب).
     */
    public function printSent(Shipment $shipment): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $shipment->office_id === (int) $office->id, 403);

        $shipment->load([
            'office:id,name,branch_name',
            'parcels' => fn ($q) => $q->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')->orderBy('created_at'),
            'parcels.destinationOffice:id,name,branch_name',
        ]);

        return view('office.shipments.sent-print', compact('office', 'shipment'));
    }

    public function dispatch(Request $request, Shipment $shipment): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        try {
            $updated = $this->shipmentService->dispatch($shipment, $office);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'       => true,
                    'message'  => "تم شحن الكشف #{$updated->shipment_number} وتحويل طروده إلى (في الطريق).",
                    'shipment' => $updated,
                ]);
            }

            return back()->with('success', "تم شحن الكشف #{$updated->shipment_number} بنجاح.");
        } catch (DomainException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Shipment $shipment): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        try {
            $updated = $this->shipmentService->cancel($shipment, $office);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'       => true,
                    'message'  => "تم إلغاء الشحنة #{$updated->shipment_number} وإعادة طرودها إلى (في الانتظار).",
                    'shipment' => $updated,
                ]);
            }

            return redirect()
                ->route('office.shipments.sent.index')
                ->with('success', "تم إلغاء الشحنة #{$updated->shipment_number} بنجاح.");
        } catch (DomainException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * شاشة الشحنات المستلمة (القادمة لهذا المكتب من فروع شريكة — يعرض فقط طرود هذا المكتب داخل كل شحنة).
     */
    public function receivedIndex(Request $request): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $tab = (string) $request->query('tab', 'pending'); // pending | completed | all

        $shipments = Shipment::with([
            'office:id,name,branch_name',
            'parcels' => fn($q) => $q->where('destination_office_id', $office->id)
                ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value),
        ])
            ->whereIn('status', [
                ShipmentStatus::IN_TRANSIT->value,
                ShipmentStatus::PARTIALLY_RECEIVED->value,
                ShipmentStatus::RECEIVED->value,
            ])
            ->whereHas('parcels', function ($q) use ($office) {
                $q->where('destination_office_id', $office->id)
                    ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value);
            })
            ->latest('dispatched_at')
            ->limit(100)
            ->get()
            ->map(function (Shipment $shipment) {
                $myParcels = $shipment->parcels;
                $totalCount = $myParcels->count();
                $receivedCount = $myParcels->where('status', OutgoingParcelStatus::RECEIVED)->count();
                $pendingCount = $myParcels->where('status', OutgoingParcelStatus::IN_TRANSIT)->count();

                $paidSum = (float) $myParcels->where('payment_type', OutgoingPaymentType::PAID)->sum('cod_amount');
                $deferredSum = (float) $myParcels->where('payment_type', OutgoingPaymentType::DEFERRED)->sum('cod_amount');

                $shipment->setAttribute('office_parcels_total', $totalCount);
                $shipment->setAttribute('office_parcels_received', $receivedCount);
                $shipment->setAttribute('office_parcels_pending', $pendingCount);
                $shipment->setAttribute('office_paid_total', $paidSum);
                $shipment->setAttribute('office_deferred_total', $deferredSum);
                $shipment->setAttribute('office_grand_total', $paidSum + $deferredSum);

                return $shipment;
            });

        $pendingReceivedShipmentsCount = $shipments->filter(fn($s) => $s->office_parcels_pending > 0)->count();

        $filteredShipments = match ($tab) {
            'completed' => $shipments->filter(fn($s) => $s->office_parcels_pending === 0)->values(),
            'all'       => $shipments->values(),
            default     => $shipments->filter(fn($s) => $s->office_parcels_pending > 0)->values(),
        };

        return view('office.shipments.received', compact(
            'office',
            'filteredShipments',
            'tab',
            'pendingReceivedShipmentsCount'
        ));
    }

    /**
     * شاشة استلام وفحص طرود شحنة قادمة بالباركود + اختيار قناة الإشعار.
     */
    public function showReceived(Shipment $shipment): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $myParcels = OutgoingParcel::query()
            ->where('shipment_id', $shipment->id)
            ->where('destination_office_id', $office->id)
            ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
            ->orderByRaw("CASE WHEN status = 'in_transit' THEN 0 ELSE 1 END")
            ->orderBy('receipt_number')
            ->get();

        abort_if($myParcels->isEmpty(), 403, 'لا توجد طرود موجهة لمكتبكم ضمن هذه الشحنة.');

        $shipment->load('office:id,name,branch_name');

        $pendingReceivedShipmentsCount = ShipmentService::countPendingReceivedShipments($office);

        return view('office.shipments.received-show', compact(
            'office',
            'shipment',
            'myParcels',
            'pendingReceivedShipmentsCount'
        ));
    }

    /**
     * استلام طرد فردي داخل الشحنة عبر مسح الباركود (أو نقر يدوي) مع تحديد قناة الإشعار.
     */
    public function receiveParcel(Request $request, Shipment $shipment): JsonResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $validated = $request->validate([
            'code'    => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'in:sms,whatsapp,both'],
        ]);

        $channel = $validated['channel'] ?? 'both';

        try {
            $result = $this->shipmentService->receiveParcelByScan(
                $shipment,
                $office,
                $validated['code'],
                $channel,
                true
            );

            $channelLabel = match ($channel) {
                'sms'      => 'SMS',
                'whatsapp' => 'واتساب',
                default    => 'SMS + واتساب',
            };

            return response()->json([
                'ok'                      => true,
                'message'                 => "تم استلام سند #{$result['outgoing_parcel']->receipt_number} وإرسال إشعار ({$channelLabel}).",
                'parcel_id'               => $result['outgoing_parcel']->id,
                'receipt_number'          => $result['outgoing_parcel']->receipt_number,
                'incoming_parcel_id'      => $result['incoming_parcel_id'],
                'remaining_in_shipment'   => $result['remaining_in_shipment'],
                'pending_shipments_count' => $result['pending_shipments_count'],
                'shipment_status'         => $result['shipment_status'],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * صفحة طباعة سند الطرد الصادر مع الباركود (QR + Barcode).
     */
    public function printParcelReceipt(OutgoingParcel $outgoingParcel): View
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $outgoingParcel->office_id === (int) $office->id, 403);

        $outgoingParcel->load(['office:id,name,branch_name,whatsapp_sender_phone', 'destinationOffice:id,name,branch_name']);

        return view('office.outgoing_parcels.receipt', compact('office', 'outgoingParcel'));
    }

    protected function serializeParcelForShipmentJs(OutgoingParcel $p): array
    {
        $paymentEnum = $p->payment_type instanceof OutgoingPaymentType
            ? $p->payment_type
            : (OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? OutgoingPaymentType::PAID);

        return [
            'id'              => (string) $p->id,
            'receipt_number'  => (string) ($p->receipt_number ?? ''),
            'package_type'    => (string) $p->package_type,
            'destination'     => $p->destinationLabel(),
            'is_external'     => $p->isExternal(),
            'sender_name'     => (string) $p->sender_name,
            'sender_phone'    => (string) $p->sender_phone,
            'recipient_name'  => (string) ($p->recipient_name ?: 'بدون اسم'),
            'recipient_phone' => (string) $p->recipient_phone,
            'payment_type'    => $paymentEnum->value,
            'payment_label'   => $paymentEnum->label(),
            'cod_amount'      => (float) ($p->cod_amount ?? 0),
        ];
    }
}
