<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use App\Services\OutgoingParcelReceiptPdfService;
use App\Services\ShipmentService;
use App\Services\WhatsAppService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Vary'          => 'Accept, X-Requested-With',
    ];

    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    /**
     * شاشة الشحنات المرسلة (الصادرة) للمكتب + تجهيز الطرود المتاحة للضم + التصفية الفورية AJAX.
     */
    public function sentIndex(Request $request): Response|JsonResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $statusFilter = (string) $request->query('status', 'all');
        $rawSearch = trim((string) $request->query('q', ''));
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $search = str_replace($eastern, $western, $rawSearch);

        $counts = $this->getSentStatusCounts($office);

        $query = Shipment::withCount([
            'parcels as received_parcels_count' => fn ($q) => $q->where(function ($sub) {
                $sub->whereNotNull('linked_incoming_parcel_id')
                    ->orWhereIn('status', [
                        OutgoingParcelStatus::RECEIVED->value,
                        OutgoingParcelStatus::DELIVERED->value,
                    ]);
            }),
        ])->where('office_id', $office->id);

        if ($statusFilter !== 'all' && ShipmentStatus::tryFrom($statusFilter) !== null) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('driver_phone', 'like', "%{$search}%");
            });
        }

        $shipments = $query->latest()->paginate(20)->appends($request->except(['_t', 'page']));

        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json([
                    'ok'            => true,
                    'status_filter' => $statusFilter,
                    'search'        => $search,
                    'counts'        => $counts,
                    'html'          => view('office.shipments.partials.sent-cards-list', compact('shipments'))->render(),
                ])
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        // الطرود الصادرة الجاهزة (pending وغير مضمومة لشحنة) لضمها بالمسح أو الاختيار اليدوي
        $availableParcels = $this->getScannableParcelsPayload($office);

        // لقطة الشحنات الأولية لبذر IndexedDB في أول زيارة وتشغيل التصفية والطباعة الأوفلاين بـ 0ms
        $initialOfflineShipments = Shipment::with([
            'office:id,name,branch_name',
            'parcels' => fn ($q) => $q->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                ->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')
                ->orderBy('created_at'),
            'parcels.destinationOffice:id,name,branch_name',
        ])
            ->where('office_id', $office->id)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Shipment $s) => $this->serializeShipmentForOffline($s))
            ->values()
            ->all();

        return response()
            ->view('office.shipments.sent', compact(
                'office',
                'shipments',
                'counts',
                'statusFilter',
                'search',
                'availableParcels',
                'initialOfflineShipments'
            ))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * استعلام موحد واحد غير مفلتر لحساب عدادات تبويبات الشحنات المرسلة.
     *
     * @return array{all:int, draft:int, in_transit:int, partially_received:int, received:int, cancelled:int}
     */
    protected function getSentStatusCounts(Office $office): array
    {
        $rawCounts = DB::table('shipments')
            ->where('office_id', $office->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $draft = (int) ($rawCounts[ShipmentStatus::DRAFT->value] ?? 0);
        $inTransit = (int) ($rawCounts[ShipmentStatus::IN_TRANSIT->value] ?? 0);
        $partiallyReceived = (int) ($rawCounts[ShipmentStatus::PARTIALLY_RECEIVED->value] ?? 0);
        $received = (int) ($rawCounts[ShipmentStatus::RECEIVED->value] ?? 0);
        $cancelled = (int) ($rawCounts[ShipmentStatus::CANCELLED->value] ?? 0);

        return [
            'all'                => $draft + $inTransit + $partiallyReceived + $received + $cancelled,
            'draft'              => $draft,
            'in_transit'         => $inTransit,
            'partially_received' => $partiallyReceived,
            'received'           => $received,
            'cancelled'          => $cancelled,
        ];
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
            'client_uuid'  => ['nullable', 'uuid'],
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
            $statusCode = $shipment->wasRecentlyCreated ? 201 : 200;

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'                => true,
                    'idempotent_replay' => ! $shipment->wasRecentlyCreated,
                    'message'           => "تم إنشاء كشف الشحنة رقم #{$shipment->shipment_number} بنجاح.",
                    'shipment'          => $this->serializeShipmentForOffline($shipment),
                    'redirect_url'      => route('office.shipments.sent.show', $shipment),
                ], $statusCode);
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
     * نقطة نهاية التمهيد السريع (Offline Bootstrap):
     * تعيد آخر الشحنات المرسلة مع لقطة طرودها الجاهزة للعرض والطباعة A4 أوفلاين + الطرود القابلة للمسح الضوئي.
     */
    public function bootstrapSent(Request $request): JsonResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $shipments = Shipment::with([
            'office:id,name,branch_name',
            'parcels' => fn ($q) => $q->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                ->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')
                ->orderBy('created_at'),
            'parcels.destinationOffice:id,name,branch_name',
        ])
            ->where('office_id', $office->id)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Shipment $shipment) => $this->serializeShipmentForOffline($shipment))
            ->values()
            ->all();

        $scannableParcels = $this->getScannableParcelsPayload($office);

        return response()
            ->json([
                'ok'                => true,
                'office'            => [
                    'id'          => (int) $office->id,
                    'name'        => (string) $office->name,
                    'branch_name' => (string) ($office->branch_name ?? ''),
                ],
                'shipments'         => $shipments,
                'scannable_parcels' => $scannableParcels,
                'counts'            => $this->getSentStatusCounts($office),
                'server_time'       => now()->toIso8601String(),
            ])
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * مزامنة طابور عمليات الشحنات الأوفلاين (create_shipment, detach_parcel, cancel_shipment) بالترتيب مع حماية عدم التكرار.
     */
    public function syncSent(Request $request): JsonResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $validated = $request->validate([
            'operations'   => ['present', 'array', 'max:100'],
            'operations.*' => ['required', 'array'],
        ]);

        $results = [];

        foreach ($validated['operations'] as $index => $op) {
            $opId = trim((string) ($op['op_id'] ?? ('op_' . $index)));
            $type = trim((string) ($op['type'] ?? $op['action'] ?? ''));
            $payload = is_array($op['payload'] ?? null) ? $op['payload'] : $op;
            $clientUuid = strtolower(trim((string) ($op['client_uuid'] ?? $payload['client_uuid'] ?? '')));

            try {
                if ($type === 'create_shipment') {
                    $createData = [
                        'client_uuid'  => $clientUuid !== '' ? $clientUuid : null,
                        'driver_name'  => $payload['driver_name'] ?? '',
                        'driver_phone' => $payload['driver_phone'] ?? '',
                        'parcel_ids'   => $payload['parcel_ids'] ?? [],
                        'dispatch_now' => $payload['dispatch_now'] ?? true,
                        'notes'        => $payload['notes'] ?? null,
                    ];

                    $shipment = $this->shipmentService->create($office, $createData);
                    $shipment->load([
                        'office:id,name,branch_name',
                        'parcels' => fn ($q) => $q->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                            ->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')
                            ->orderBy('created_at'),
                        'parcels.destinationOffice:id,name,branch_name',
                    ]);

                    $results[] = [
                        'op_id'             => $opId,
                        'type'              => $type,
                        'client_uuid'       => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                        'status'            => 'synced',
                        'idempotent_replay' => ! $shipment->wasRecentlyCreated,
                        'message'           => "تمت مزامنة كشف الشحنة #{$shipment->shipment_number} بنجاح.",
                        'shipment'          => $this->serializeShipmentForOffline($shipment),
                    ];
                } elseif ($type === 'detach_parcel') {
                    $shipmentRef = trim((string) ($op['shipment_id'] ?? $payload['shipment_id'] ?? $clientUuid));
                    $parcelId = trim((string) ($op['parcel_id'] ?? $payload['parcel_id'] ?? ''));

                    $shipment = $this->resolveOfficeShipment($office, $shipmentRef, $clientUuid);
                    if (! $shipment) {
                        $results[] = [
                            'op_id'       => $opId,
                            'type'        => $type,
                            'client_uuid' => $clientUuid !== '' ? $clientUuid : null,
                            'status'      => 'conflict',
                            'message'     => 'لم يتم العثور على الشحنة المطلوبة.',
                            'shipment'    => null,
                        ];
                        continue;
                    }

                    $parcel = OutgoingParcel::query()
                        ->where('office_id', $office->id)
                        ->whereKey($parcelId)
                        ->first();

                    if (! $parcel) {
                        $results[] = [
                            'op_id'       => $opId,
                            'type'        => $type,
                            'client_uuid' => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'      => 'conflict',
                            'message'     => 'لم يتم العثور على الطرد المطلوب.',
                            'shipment'    => $this->loadAndSerializeShipment($shipment),
                        ];
                        continue;
                    }

                    // حماية عدم التكرار (Idempotent Replay): إذا كان الطرد مفصولاً بالفعل وفي حالة الانتظار
                    if ($parcel->shipment_id === null && $parcel->status === OutgoingParcelStatus::PENDING) {
                        $results[] = [
                            'op_id'             => $opId,
                            'type'              => $type,
                            'client_uuid'       => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'            => 'synced',
                            'idempotent_replay' => true,
                            'parcel_id'         => (string) $parcel->id,
                            'message'           => "الطرد (سند #{$parcel->receipt_number}) مفصول مسبقاً.",
                            'shipment'          => $this->loadAndSerializeShipment($shipment),
                        ];
                        continue;
                    }

                    try {
                        $updatedParcel = $this->shipmentService->detachParcel($shipment, $parcel, $office);

                        $results[] = [
                            'op_id'             => $opId,
                            'type'              => $type,
                            'client_uuid'       => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'            => 'synced',
                            'idempotent_replay' => false,
                            'parcel_id'         => (string) $updatedParcel->id,
                            'message'           => "تم إخراج الطرد (سند #{$updatedParcel->receipt_number}) من الشحنة.",
                            'shipment'          => $this->loadAndSerializeShipment($shipment),
                        ];
                    } catch (DomainException $e) {
                        $results[] = [
                            'op_id'       => $opId,
                            'type'        => $type,
                            'client_uuid' => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'      => 'conflict',
                            'parcel_id'   => (string) $parcel->id,
                            'message'     => $e->getMessage(),
                            'shipment'    => $this->loadAndSerializeShipment($shipment),
                        ];
                    }
                } elseif ($type === 'cancel_shipment') {
                    $shipmentRef = trim((string) ($op['shipment_id'] ?? $payload['shipment_id'] ?? $clientUuid));
                    $shipment = $this->resolveOfficeShipment($office, $shipmentRef, $clientUuid);

                    if (! $shipment) {
                        $results[] = [
                            'op_id'       => $opId,
                            'type'        => $type,
                            'client_uuid' => $clientUuid !== '' ? $clientUuid : null,
                            'status'      => 'conflict',
                            'message'     => 'لم يتم العثور على الشحنة المطلوبة للإلغاء.',
                            'shipment'    => null,
                        ];
                        continue;
                    }

                    // حماية عدم التكرار: إذا كانت الشحنة ملغاة مسبقاً نُرجع نجاحاً بدون خطأ
                    if ($shipment->status === ShipmentStatus::CANCELLED) {
                        $results[] = [
                            'op_id'             => $opId,
                            'type'              => $type,
                            'client_uuid'       => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'            => 'synced',
                            'idempotent_replay' => true,
                            'message'           => "الكشف #{$shipment->shipment_number} ملغي مسبقاً.",
                            'shipment'          => $this->loadAndSerializeShipment($shipment),
                        ];
                        continue;
                    }

                    try {
                        $updated = $this->shipmentService->cancel($shipment, $office);

                        $results[] = [
                            'op_id'             => $opId,
                            'type'              => $type,
                            'client_uuid'       => $updated->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'            => 'synced',
                            'idempotent_replay' => false,
                            'message'           => "تم إلغاء الكشف #{$updated->shipment_number} وإرجاع طروده للانتظار.",
                            'shipment'          => $this->loadAndSerializeShipment($updated),
                        ];
                    } catch (DomainException $e) {
                        $results[] = [
                            'op_id'       => $opId,
                            'type'        => $type,
                            'client_uuid' => $shipment->client_uuid ?? ($clientUuid !== '' ? $clientUuid : null),
                            'status'      => 'conflict',
                            'message'     => $e->getMessage(),
                            'shipment'    => $this->loadAndSerializeShipment($shipment),
                        ];
                    }
                } else {
                    $results[] = [
                        'op_id'       => $opId,
                        'type'        => $type,
                        'client_uuid' => $clientUuid !== '' ? $clientUuid : null,
                        'status'      => 'error',
                        'message'     => 'نوع عملية المزامنة غير مدعوم.',
                        'shipment'    => null,
                    ];
                }
            } catch (DomainException $e) {
                $results[] = [
                    'op_id'       => $opId,
                    'type'        => $type,
                    'client_uuid' => $clientUuid !== '' ? $clientUuid : null,
                    'status'      => 'conflict',
                    'message'     => $e->getMessage(),
                    'shipment'    => null,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'op_id'       => $opId,
                    'type'        => $type,
                    'client_uuid' => $clientUuid !== '' ? $clientUuid : null,
                    'status'      => 'error',
                    'message'     => 'حدث خطأ غير متوقع أثناء معالجة العملية.',
                    'shipment'    => null,
                ];
            }
        }

        return response()
            ->json([
                'ok'                => true,
                'results'           => $results,
                'counts'            => $this->getSentStatusCounts($office),
                'scannable_parcels' => $this->getScannableParcelsPayload($office),
                'server_time'       => now()->toIso8601String(),
            ])
            ->withHeaders(self::NO_CACHE_HEADERS);
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
        abort_unless((int) $shipment->office_id === (int) $office->id, 403);

        try {
            $updated = $this->shipmentService->cancel($shipment, $office);
            $successMsg = "تم إلغاء الكشف #{$updated->shipment_number} وإرجاع جميع طروده إلى حالة (في الانتظار) بنجاح.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'       => true,
                    'message'  => $successMsg,
                    'shipment' => $updated,
                ]);
            }

            return redirect()
                ->route('office.shipments.sent.index')
                ->with('success', $successMsg);
        } catch (DomainException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * إخراج طرد واحد غير مستلم من الشحنة وإعادته إلى حالة (في الانتظار).
     */
    public function detachParcel(Request $request, Shipment $shipment, OutgoingParcel $outgoingParcel): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $shipment->office_id === (int) $office->id, 403);

        try {
            $updated = $this->shipmentService->detachParcel($shipment, $outgoingParcel, $office);
            $msg = "تم إخراج الطرد (سند #{$updated->receipt_number}) من الشحنة وإعادته إلى (في الانتظار).";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'        => true,
                    'message'   => $msg,
                    'parcel_id' => $updated->id,
                    'status'    => $updated->status->value,
                ]);
            }

            return back()->with('success', $msg);
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
    public function receivedIndex(Request $request): Response
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $tab = (string) $request->query('tab', 'all'); // all | pending | completed
        if (! in_array($tab, ['all', 'pending', 'completed'], true)) {
            $tab = 'all';
        }

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

        $pendingReceivedShipmentsCount = $shipments->filter(fn ($s) => $s->office_parcels_pending > 0)->count();
        $completedReceivedShipmentsCount = $shipments->count() - $pendingReceivedShipmentsCount;
        $receivedCounts = [
            'all'       => $shipments->count(),
            'pending'   => $pendingReceivedShipmentsCount,
            'completed' => $completedReceivedShipmentsCount,
        ];

        $filteredShipments = match ($tab) {
            'pending'   => $shipments->filter(fn ($s) => $s->office_parcels_pending > 0)->values(),
            'completed' => $shipments->filter(fn ($s) => $s->office_parcels_pending === 0)->values(),
            default     => $shipments->values(),
        };

        return response()
            ->view('office.shipments.received', compact(
                'office',
                'filteredShipments',
                'tab',
                'pendingReceivedShipmentsCount',
                'receivedCounts'
            ))
            ->withHeaders(self::NO_CACHE_HEADERS);
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

        $outgoingParcel->load([
            'office:id,name,branch_name,whatsapp_sender_phone,manager_phone,httpsms_from_phone',
            'destinationOffice:id,name,branch_name,whatsapp_sender_phone,manager_phone',
        ]);

        return view('office.outgoing_parcels.receipt', compact('office', 'outgoingParcel'));
    }

    /**
     * توليد وتنزيل/بث سند الطرد الصادر كملف PDF عربي RTL (للمشاركة أو التحميل).
     */
    public function downloadParcelReceiptPdf(
        OutgoingParcel $outgoingParcel,
        OutgoingParcelReceiptPdfService $pdfService
    ): Response {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $outgoingParcel->office_id === (int) $office->id, 403);

        $pdfBytes = $pdfService->generate($outgoingParcel);
        $filename = $pdfService->filename($outgoingParcel);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($pdfBytes),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }

    /**
     * توليد سند الطرد الصادر كملف PDF وإرساله مباشرة للمستلم عبر خدمة واتساب المكتب (Evolution Go).
     */
    public function sendParcelReceiptWhatsApp(
        OutgoingParcel $outgoingParcel,
        OutgoingParcelReceiptPdfService $pdfService,
        WhatsAppService $whatsAppService
    ): JsonResponse {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        abort_unless((int) $outgoingParcel->office_id === (int) $office->id, 403);

        $recipientPhone = trim((string) ($outgoingParcel->recipient_phone ?? ''));
        if ($recipientPhone === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'لا يوجد رقم هاتف مسجل للمستلم على هذا الطرد.',
            ], 422);
        }

        if (! $whatsAppService->isOfficeConnected($office)) {
            return response()->json([
                'ok'      => false,
                'message' => 'يرجى ربط وتفعيل واتساب المكتب أولاً من إعدادات الواتساب.',
            ], 422);
        }

        try {
            $outgoingParcel->loadMissing([
                'office:id,name,branch_name,whatsapp_sender_phone,manager_phone,httpsms_from_phone',
                'destinationOffice:id,name,branch_name,whatsapp_sender_phone,manager_phone',
            ]);

            $pdfBytes = $pdfService->generate($outgoingParcel);
            $filename = $pdfService->filename($outgoingParcel);

            $officeLabel = trim($office->name . ($office->branch_name ? " ({$office->branch_name})" : ''));
            $destinationLabel = $outgoingParcel->destinationLabel();
            $receiptNum = trim((string) ($outgoingParcel->receipt_number ?: $outgoingParcel->id));
            $caption = "سند إرسال طرد #{$receiptNum} من {$officeLabel} إلى {$destinationLabel}";

            $result = $whatsAppService
                ->forOffice($office)
                ->sendDocument($recipientPhone, $pdfBytes, $filename, $caption, 'application/pdf');

            if (empty($result['success']) || ! empty($result['error'])) {
                $rawError = strtolower((string) ($result['message'] ?? data_get($result, 'data.error', '')));

                Log::error('Failed to send outgoing parcel receipt PDF via WhatsApp', [
                    'office_id'  => $office->id,
                    'parcel_id'  => $outgoingParcel->id,
                    'recipient'  => $recipientPhone,
                    'wa_result'  => $result,
                ]);

                if (
                    str_contains($rawError, 'not logged')
                    || str_contains($rawError, 'disconnected')
                    || str_contains($rawError, 'not connected')
                    || str_contains($rawError, 'no session')
                ) {
                    Cache::put('whatsapp_status_office_' . $office->id, 'offline', now()->addMinutes(15));

                    return response()->json([
                        'ok'      => false,
                        'message' => 'يرجى ربط وتفعيل واتساب المكتب أولاً من إعدادات الواتساب.',
                    ], 422);
                }

                return response()->json([
                    'ok'      => false,
                    'message' => 'تعذر إرسال السند عبر واتساب حالياً، يرجى المحاولة لاحقاً.',
                ], 502);
            }

            Cache::put('whatsapp_status_office_' . $office->id, 'online', now()->addMinutes(15));

            return response()->json([
                'ok'      => true,
                'message' => 'تم إرسال السند كملف PDF للمستلم عبر واتساب بنجاح.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception while sending outgoing parcel receipt PDF via WhatsApp', [
                'office_id' => $office->id,
                'parcel_id' => $outgoingParcel->id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'حدث خطأ أثناء تجهيز أو إرسال السند عبر واتساب، يرجى المحاولة مجدداً.',
            ], 500);
        }
    }

    protected function resolveOfficeShipment(Office $office, string $shipmentRef, string $clientUuid = ''): ?Shipment
    {
        $cleanRef = preg_replace('/^srv_/i', '', trim($shipmentRef)) ?? trim($shipmentRef);
        $cleanUuid = trim($clientUuid);

        if ($cleanRef === '' && $cleanUuid === '') {
            return null;
        }

        return Shipment::query()
            ->where('office_id', $office->id)
            ->where(function ($q) use ($cleanRef, $cleanUuid) {
                if ($cleanRef !== '') {
                    $q->where('id', $cleanRef)->orWhere('client_uuid', $cleanRef);
                }
                if ($cleanUuid !== '') {
                    $q->orWhere('client_uuid', $cleanUuid);
                }
            })
            ->first();
    }

    protected function loadAndSerializeShipment(Shipment $shipment): array
    {
        $fresh = Shipment::with([
            'office:id,name,branch_name',
            'parcels' => fn ($q) => $q->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                ->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')
                ->orderBy('created_at'),
            'parcels.destinationOffice:id,name,branch_name',
        ])->findOrFail($shipment->id);

        return $this->serializeShipmentForOffline($fresh);
    }

    protected function getScannableParcelsPayload(Office $office): array
    {
        return OutgoingParcel::with('destinationOffice:id,name,branch_name')
            ->where('office_id', $office->id)
            ->where('status', OutgoingParcelStatus::PENDING->value)
            ->whereNull('shipment_id')
            ->latest('created_at')
            ->limit(300)
            ->get()
            ->map(fn (OutgoingParcel $p) => $this->serializeParcelForShipmentJs($p))
            ->values()
            ->all();
    }

    protected function serializeShipmentForOffline(Shipment $shipment): array
    {
        $statusEnum = $shipment->status instanceof ShipmentStatus
            ? $shipment->status
            : (ShipmentStatus::tryFrom((string) $shipment->status) ?? ShipmentStatus::IN_TRANSIT);

        $rawParcels = $shipment->relationLoaded('parcels')
            ? $shipment->parcels
            : $shipment->parcels()
                ->with('destinationOffice:id,name,branch_name')
                ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                ->orderByRaw('CAST(receipt_number AS UNSIGNED) ASC')
                ->orderBy('created_at')
                ->get();

        $activeParcels = $rawParcels->filter(function (OutgoingParcel $p): bool {
            $pStatus = $p->status instanceof OutgoingParcelStatus ? $p->status->value : (string) $p->status;
            return $pStatus !== OutgoingParcelStatus::CANCELLED->value;
        })->values();

        $receivedCount = $activeParcels->filter(function (OutgoingParcel $p): bool {
            $pStatus = $p->status instanceof OutgoingParcelStatus
                ? $p->status
                : OutgoingParcelStatus::tryFrom((string) $p->status);

            return $p->linked_incoming_parcel_id !== null
                || in_array($pStatus, [OutgoingParcelStatus::RECEIVED, OutgoingParcelStatus::DELIVERED], true);
        })->count();

        $serializedParcels = $activeParcels
            ->map(fn (OutgoingParcel $p) => $this->serializeParcelForShipmentJs($p))
            ->values()
            ->all();

        $clientUuid = $shipment->client_uuid ? (string) $shipment->client_uuid : null;

        return [
            'id'                     => (string) $shipment->id,
            'client_uuid'            => $clientUuid,
            'local_key'              => $clientUuid ?: ('srv_' . $shipment->id),
            'office_id'              => (int) $shipment->office_id,
            'shipment_number'        => (string) $shipment->shipment_number,
            'driver_name'            => (string) $shipment->driver_name,
            'driver_phone'           => (string) $shipment->driver_phone,
            'parcels_count'          => (int) $shipment->parcels_count,
            'received_parcels_count' => $receivedCount,
            'total_paid_amount'      => (float) $shipment->total_paid_amount,
            'total_deferred_amount'  => (float) $shipment->total_deferred_amount,
            'total_amount'           => (float) $shipment->total_amount,
            'destinations_summary'   => array_values((array) ($shipment->destinations_summary ?? [])),
            'status'                 => $statusEnum->value,
            'status_label'           => $statusEnum->label(),
            'notes'                  => $shipment->notes ? (string) $shipment->notes : null,
            'dispatched_at'          => $shipment->dispatched_at?->toIso8601String(),
            'completed_at'           => $shipment->completed_at?->toIso8601String(),
            'cancelled_at'           => $shipment->cancelled_at?->toIso8601String(),
            'created_at'             => $shipment->created_at?->toIso8601String(),
            'pending_sync'           => false,
            'can_cancel'             => $statusEnum !== ShipmentStatus::CANCELLED && $receivedCount === 0,
            'show_url'               => route('office.shipments.sent.show', $shipment),
            'print_url'              => route('office.shipments.sent.print', $shipment),
            'parcels'                => $serializedParcels,
        ];
    }

    protected function serializeParcelForShipmentJs(OutgoingParcel $p): array
    {
        $paymentEnum = $p->payment_type instanceof OutgoingPaymentType
            ? $p->payment_type
            : (OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? OutgoingPaymentType::PAID);

        $statusEnum = $p->status instanceof OutgoingParcelStatus
            ? $p->status
            : (OutgoingParcelStatus::tryFrom((string) $p->status) ?? OutgoingParcelStatus::PENDING);

        $destinationLabel = $p->destinationLabel();

        return [
            'id'                => (string) $p->id,
            'office_id'         => (int) $p->office_id,
            'receipt_number'    => (string) ($p->receipt_number ?? ''),
            'package_type'      => (string) ($p->package_type ?: 'طرد'),
            'destination'       => $destinationLabel,
            'destination_label' => $destinationLabel,
            'is_external'       => $p->isExternal(),
            'sender_name'       => (string) ($p->sender_name ?? ''),
            'sender_phone'      => (string) ($p->sender_phone ?? ''),
            'recipient_name'    => (string) ($p->recipient_name ?: 'بدون اسم'),
            'recipient_phone'   => (string) ($p->recipient_phone ?? ''),
            'payment_type'      => $paymentEnum->value,
            'payment_label'     => $paymentEnum->label(),
            'cod_amount'        => (float) ($p->cod_amount ?? 0),
            'status'            => $statusEnum->value,
            'status_label'      => $statusEnum->label(),
            'is_received'       => $p->linked_incoming_parcel_id !== null
                || in_array($statusEnum, [OutgoingParcelStatus::RECEIVED, OutgoingParcelStatus::DELIVERED], true),
        ];
    }
}
