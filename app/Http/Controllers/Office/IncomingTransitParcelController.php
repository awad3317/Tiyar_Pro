<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Services\OutgoingParcelService;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomingTransitParcelController extends Controller
{
    private const SELECT_COLUMNS = [
        'outgoing_parcels.id',
        'outgoing_parcels.office_id',
        'outgoing_parcels.external_partner_link_id',
        'outgoing_parcels.shipment_id',
        'outgoing_parcels.destination_office_id',
        'outgoing_parcels.sender_name',
        'outgoing_parcels.sender_phone',
        'outgoing_parcels.recipient_name',
        'outgoing_parcels.recipient_phone',
        'outgoing_parcels.package_type',
        'outgoing_parcels.receipt_number',
        'outgoing_parcels.cod_amount',
        'outgoing_parcels.payment_type',
        'outgoing_parcels.notes',
        'outgoing_parcels.status',
        'outgoing_parcels.dispatched_at',
        'outgoing_parcels.created_at',
        'shipments.shipment_number as shipment_number',
        'shipments.driver_name as shipment_driver_name',
        'shipments.driver_phone as shipment_driver_phone',
    ];

    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Vary'          => 'Accept, X-Requested-With',
    ];

    public function index(Request $request)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $transitParcels = OutgoingParcel::query()
            ->leftJoin('shipments', 'shipments.id', '=', 'outgoing_parcels.shipment_id')
            ->select(self::SELECT_COLUMNS)
            ->with([
                'office:id,name,branch_name',
                'externalPartnerLink:id,partner_name,partner_city',
            ])
            ->where('outgoing_parcels.destination_office_id', $office->id)
            ->where('outgoing_parcels.status', OutgoingParcelStatus::IN_TRANSIT)
            ->whereNull('shipments.rejected_at')
            ->latest('outgoing_parcels.dispatched_at')
            ->get();

        $transitPayloads = $this->buildTransitPayloads($transitParcels, (int) $office->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json([
                    'ok'              => true,
                    'office_id'       => (int) $office->id,
                    'remaining_count' => $transitParcels->count(),
                    'items'           => array_values($transitPayloads),
                    'parcels'         => $transitParcels,
                ])
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        return response()
            ->view('office.transit_parcels.index', compact('office', 'transitParcels', 'transitPayloads'))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * تجهيز حمولة موحدة للطرود القادمة في الطريق للعرض السريع والتخزين المحلي أوفلاين (IndexedDB)
     * دون أي استعلامات SQL إضافية.
     */
    private function buildTransitPayloads($transitParcels, int $officeId): array
    {
        $payloads = [];

        foreach ($transitParcels as $p) {
            $officeLabel = $p->senderOfficeLabel();

            $sourceRegion = null;
            if ($p->office) {
                $branch = trim((string) ($p->office->branch_name ?? ''));
                $sourceRegion = $branch !== '' ? $branch : null;
            } elseif ($p->externalPartnerLink) {
                $city = trim((string) ($p->externalPartnerLink->partner_city ?? ''));
                $sourceRegion = $city !== '' ? $city : null;
            }

            $paymentEnum = $p->payment_type instanceof OutgoingPaymentType
                ? $p->payment_type
                : (OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? OutgoingPaymentType::PAID);

            $dispatchedAt = $p->dispatched_at;
            $dispatchedAgo = $dispatchedAt ? $dispatchedAt->copy()->locale('ar')->diffForHumans() : null;
            $dispatchedClock = $dispatchedAt
                ? $dispatchedAt->format('g:i') . ' ' . ($dispatchedAt->format('A') === 'AM' ? 'ص' : 'م')
                : null;
            $dispatchedDate = ($dispatchedAt && ! $dispatchedAt->isToday()) ? $dispatchedAt->format('Y/m/d') : null;

            $shipmentId     = (string) ($p->shipment_id ?? '');
            $shipmentNumber = (string) ($p->shipment_number ?? '');
            $driverName     = (string) ($p->shipment_driver_name ?? '');
            $driverPhone    = (string) ($p->shipment_driver_phone ?? '');
            $codRaw         = (float) ($p->cod_amount ?? 0);

            $payloads[(string) $p->id] = [
                'id'                    => (string) $p->id,
                'destination_office_id' => (int) ($p->destination_office_id ?: $officeId),
                'source_office_id'      => $p->office_id ? (int) $p->office_id : null,
                'source_region'         => $sourceRegion,
                'url'                   => route('office.transit-parcels.receive', $p, false),
                'package_type'          => (string) $p->package_type,
                'receipt_number'        => (string) ($p->receipt_number ?? ''),
                'source_office'         => $officeLabel,
                'shipment_id'           => $shipmentId,
                'shipment_number'       => $shipmentNumber,
                'driver_name'           => $driverName,
                'driver_phone'          => $driverPhone,
                'payment_type'          => $paymentEnum->value,
                'payment_label'         => $paymentEnum->label(),
                'payment_badge_class'   => $paymentEnum->badgeClass(),
                'is_deferred'           => $paymentEnum->value === 'deferred',
                'cod_amount_raw'        => $codRaw,
                'cod_amount'            => number_format($codRaw),
                'recipient_name'        => (string) ($p->recipient_name ?: 'بدون اسم'),
                'recipient_name_raw'    => $p->recipient_name ? (string) $p->recipient_name : null,
                'recipient_phone'       => (string) $p->recipient_phone,
                'sender_name'           => (string) $p->sender_name,
                'sender_phone'          => (string) $p->sender_phone,
                'notes'                 => (string) ($p->notes ?? ''),
                'dispatched_at'         => $dispatchedAt ? $dispatchedAt->toIso8601String() : null,
                'dispatched_date'       => $dispatchedDate,
                'dispatched_ago'        => $dispatchedAgo,
                'dispatched_clock'      => $dispatchedClock,
                'search_text'           => implode(' ', array_filter([
                    $p->package_type,
                    $p->receipt_number,
                    $p->recipient_name,
                    $p->recipient_phone,
                    $p->sender_name,
                    $p->sender_phone,
                    $officeLabel,
                    $shipmentNumber,
                    $driverName,
                    $driverPhone,
                    $p->notes,
                ])),
            ];
        }

        return $payloads;
    }

    public function receive(Request $request, OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->destination_office_id !== (int) $office->id) {
            abort(403, 'هذا الطرد غير موجّه إلى مكتبكم.');
        }

        $validated = $request->validate([
            'channel'     => ['nullable', 'in:sms,whatsapp,both'],
            'slot_number' => ['nullable', 'string', 'max:50'],
        ]);

        $channel = $validated['channel'] ?? 'both';
        $slotNumber = $validated['slot_number'] ?? null;

        try {
            $incomingParcel = $service->receiveAtDestination(
                $outgoingParcel,
                $office,
                afterResponse: true,
                channel: $channel,
                slotNumber: $slotNumber
            );

            $channelLabel = match ($channel) {
                'sms'      => 'SMS',
                'whatsapp' => 'واتساب',
                default    => 'كلاهما',
            };

            $shipmentRemaining = null;
            $shipmentNumber = null;
            if ($outgoingParcel->shipment_id) {
                $outgoingParcel->loadMissing('shipment:id,shipment_number');
                $shipmentNumber = $outgoingParcel->shipment?->shipment_number;
                $shipmentRemaining = OutgoingParcel::query()
                    ->where('shipment_id', $outgoingParcel->shipment_id)
                    ->where('destination_office_id', $office->id)
                    ->where('status', OutgoingParcelStatus::IN_TRANSIT)
                    ->count();
            }

            if (! $request->filled('channel')) {
                $successMessage = 'تم استلام الطرد بنجاح، تم تحويله إلى قائمة الطرود وإرسال الإشعار للعميل.';
            } elseif ($shipmentNumber !== null && $shipmentRemaining === 0) {
                $successMessage = "تم استلام سند #{$outgoingParcel->receipt_number} ({$channelLabel}) — اكتملت طرود كشف #{$shipmentNumber} بالكامل!";
            } else {
                $successMessage = "تم استلام سند #{$outgoingParcel->receipt_number} وتحويله إلى الطرود المستلمة ({$channelLabel}).";
            }

            if ($request->expectsJson() || $request->ajax()) {
                $remainingCount = OutgoingParcel::query()
                    ->leftJoin('shipments', 'shipments.id', '=', 'outgoing_parcels.shipment_id')
                    ->where('outgoing_parcels.destination_office_id', $office->id)
                    ->where('outgoing_parcels.status', OutgoingParcelStatus::IN_TRANSIT)
                    ->whereNull('shipments.rejected_at')
                    ->count();

                $pendingShipmentsCount = ShipmentService::countPendingReceivedShipments($office);

                return response()
                    ->json([
                        'ok'                      => true,
                        'message'                 => $successMessage,
                        'parcel_id'               => $outgoingParcel->id,
                        'incoming_id'             => $incomingParcel->id,
                        'remaining_count'         => $remainingCount,
                        'pending_shipments_count' => $pendingShipmentsCount,
                        'shipment_id'             => $outgoingParcel->shipment_id,
                        'shipment_number'         => $shipmentNumber,
                        'shipment_remaining'      => $shipmentRemaining,
                    ])
                    ->withHeaders(self::NO_CACHE_HEADERS);
            }

            return redirect()
                ->route('office.transit-parcels.index')
                ->with('success', $successMessage);
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()
                    ->json([
                        'ok'      => false,
                        'message' => $e->getMessage(),
                    ], 422)
                    ->withHeaders(self::NO_CACHE_HEADERS);
            }

            return redirect()
                ->route('office.transit-parcels.index')
                ->with('error', $e->getMessage());
        }
    }
}