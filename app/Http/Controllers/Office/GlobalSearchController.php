<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const RESULT_LIMIT = 15;

    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Vary'          => 'Accept, X-Requested-With',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Office $office */
        $office = $request->user('office');

        $normalized = $this->normalizeQuery((string) $request->query('q', ''));
        $query = $normalized['text'];

        if ($query === '') {
            return response()
                ->json([
                    'ok'         => true,
                    'query'      => '',
                    'total'      => 0,
                    'incoming'   => [],
                    'in_transit' => [],
                    'outgoing'   => [],
                ])
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        $textLike = '%' . $query . '%';
        $receiptLike = '%' . $normalized['receipt'] . '%';
        $phoneLike = '%' . $normalized['phone'] . '%';

        // 1. الطرود الواردة بالمكتب (parcels)
        $incoming = Parcel::query()
            ->select([
                'id',
                'office_id',
                'receipt_number',
                'package_type',
                'recipient_name',
                'recipient_phone',
                'source_office_name',
                'source_region',
                'cod_amount',
                'status',
                'created_at',
            ])
            ->where('office_id', $office->id)
            ->where(function ($sub) use ($textLike, $receiptLike, $phoneLike) {
                $sub->where('receipt_number', 'like', $receiptLike)
                    ->orWhere('recipient_name', 'like', $textLike)
                    ->orWhere('recipient_phone', 'like', $phoneLike);
            })
            ->latest('created_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (Parcel $p): array {
                $statusEnum = $p->status instanceof ParcelStatus
                    ? $p->status
                    : ParcelStatus::tryFrom((string) $p->status);

                $searchParam = $p->receipt_number ?: $p->recipient_phone;

                return [
                    'id'               => (string) $p->id,
                    'receipt_number'   => (string) ($p->receipt_number ?? ''),
                    'package_type'     => (string) ($p->package_type ?: 'طرد'),
                    'recipient_name'   => (string) ($p->recipient_name ?: 'بدون اسم'),
                    'recipient_phone'  => (string) ($p->recipient_phone ?? ''),
                    'status'           => $statusEnum?->value ?? (string) $p->status,
                    'status_label'     => $statusEnum?->label() ?? (string) $p->status,
                    'source_name'      => (string) ($p->source_office_name ?: ($p->source_region ?: '')),
                    'created_at_human' => $p->created_at?->copy()->locale('ar')->diffForHumans() ?? '',
                    'url'              => route('office.parcels.index', array_filter(['q' => $searchParam])),
                ];
            })
            ->values()
            ->all();

        // 2. الطرود القادمة في الطريق إلى هذا المكتب (outgoing_parcels)
        $inTransit = OutgoingParcel::query()
            ->leftJoin('shipments', 'shipments.id', '=', 'outgoing_parcels.shipment_id')
            ->select([
                'outgoing_parcels.id',
                'outgoing_parcels.office_id',
                'outgoing_parcels.external_partner_link_id',
                'outgoing_parcels.shipment_id',
                'outgoing_parcels.destination_office_id',
                'outgoing_parcels.receipt_number',
                'outgoing_parcels.package_type',
                'outgoing_parcels.sender_name',
                'outgoing_parcels.sender_phone',
                'outgoing_parcels.recipient_name',
                'outgoing_parcels.recipient_phone',
                'outgoing_parcels.cod_amount',
                'outgoing_parcels.payment_type',
                'outgoing_parcels.dispatched_at',
                'outgoing_parcels.created_at',
            ])
            ->with([
                'office:id,name,branch_name',
                'externalPartnerLink:id,partner_name,partner_city',
            ])
            ->where('outgoing_parcels.destination_office_id', $office->id)
            ->where('outgoing_parcels.status', OutgoingParcelStatus::IN_TRANSIT->value)
            ->whereNull('shipments.rejected_at')
            ->where(function ($sub) use ($textLike, $receiptLike, $phoneLike) {
                $sub->where('outgoing_parcels.receipt_number', 'like', $receiptLike)
                    ->orWhere('outgoing_parcels.sender_name', 'like', $textLike)
                    ->orWhere('outgoing_parcels.sender_phone', 'like', $phoneLike)
                    ->orWhere('outgoing_parcels.recipient_name', 'like', $textLike)
                    ->orWhere('outgoing_parcels.recipient_phone', 'like', $phoneLike);
            })
            ->latest('outgoing_parcels.dispatched_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (OutgoingParcel $p): array {
                $paymentEnum = $p->payment_type instanceof OutgoingPaymentType
                    ? $p->payment_type
                    : (OutgoingPaymentType::tryFrom((string) $p->payment_type) ?? OutgoingPaymentType::PAID);

                $fromOffice = $p->senderOfficeLabel();

                return [
                    'id'              => (string) $p->id,
                    'receipt_number'  => (string) ($p->receipt_number ?? ''),
                    'package_type'    => (string) ($p->package_type ?: 'طرد'),
                    'sender_name'     => (string) ($p->sender_name ?? ''),
                    'sender_phone'    => (string) ($p->sender_phone ?? ''),
                    'recipient_name'  => (string) ($p->recipient_name ?: 'بدون اسم'),
                    'recipient_phone' => (string) ($p->recipient_phone ?? ''),
                    'from_office'     => $fromOffice,
                    'cod_amount'      => (float) ($p->cod_amount ?? 0),
                    'cod_formatted'   => number_format((float) ($p->cod_amount ?? 0)),
                    'payment_type'    => $paymentEnum->value,
                    'payment_label'   => $paymentEnum->label(),
                    'url'             => route('office.transit-parcels.index'),
                ];
            })
            ->values()
            ->all();

        // 3. الطرود الصادرة من هذا المكتب (فقط عند تفعيل ميزة outgoing_parcels)
        $outgoing = [];
        if ($office->hasFeature('outgoing_parcels')) {
            $outgoing = OutgoingParcel::query()
                ->select([
                    'id',
                    'office_id',
                    'destination_office_id',
                    'external_office_name',
                    'receipt_number',
                    'package_type',
                    'sender_name',
                    'sender_phone',
                    'recipient_name',
                    'recipient_phone',
                    'cod_amount',
                    'payment_type',
                    'status',
                    'linked_incoming_parcel_id',
                    'created_at',
                ])
                ->with([
                    'destinationOffice:id,name,branch_name',
                    'linkedIncomingParcel:id,status',
                ])
                ->where('office_id', $office->id)
                ->where(function ($sub) use ($textLike, $receiptLike, $phoneLike) {
                    $sub->where('receipt_number', 'like', $receiptLike)
                        ->orWhere('sender_name', 'like', $textLike)
                        ->orWhere('sender_phone', 'like', $phoneLike)
                        ->orWhere('recipient_name', 'like', $textLike)
                        ->orWhere('recipient_phone', 'like', $phoneLike);
                })
                ->latest('created_at')
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(function (OutgoingParcel $p): array {
                    $linkedStatusLabel = null;
                    if ($p->status === OutgoingParcelStatus::RECEIVED && $p->linkedIncomingParcel) {
                        $linked = $p->linkedIncomingParcel->status;
                        $linkedValue = $linked instanceof ParcelStatus ? $linked->value : (string) $linked;
                        $linkedStatusLabel = match ($linkedValue) {
                            'delivered' => 'تم التسليم للعميل',
                            'returned'  => 'مُرتجع',
                            default     => 'بالمكتب المستلم',
                        };
                    }

                    $searchParam = $p->receipt_number ?: $p->recipient_phone;

                    return [
                        'id'                  => (string) $p->id,
                        'receipt_number'      => (string) ($p->receipt_number ?? ''),
                        'package_type'        => (string) ($p->package_type ?: 'طرد'),
                        'sender_name'         => (string) ($p->sender_name ?? ''),
                        'sender_phone'        => (string) ($p->sender_phone ?? ''),
                        'recipient_name'      => (string) ($p->recipient_name ?: 'بدون اسم'),
                        'recipient_phone'     => (string) ($p->recipient_phone ?? ''),
                        'destination'         => $p->destinationLabel(),
                        'is_external'         => $p->isExternal(),
                        'status'              => $p->status->value,
                        'status_label'        => $p->status->label(),
                        'linked_status_label' => $linkedStatusLabel,
                        'url'                 => route('office.outgoing-parcels.index', array_filter(['q' => $searchParam])),
                    ];
                })
                ->values()
                ->all();
        }

        $total = count($incoming) + count($inTransit) + count($outgoing);

        return response()
            ->json([
                'ok'         => true,
                'query'      => $query,
                'total'      => $total,
                'incoming'   => $incoming,
                'in_transit' => $inTransit,
                'outgoing'   => $outgoing,
            ])
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * توحيد الأرقام العربية/الفارسية وإزالة +967 والمسافات من أرقام الهواتف مع الحفاظ على أسماء الأشخاص.
     *
     * @return array{text: string, receipt: string, phone: string}
     */
    protected function normalizeQuery(string $raw): array
    {
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $clean = str_replace($eastern, $western, $raw);
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}]/u', '', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);

        if ($clean === '') {
            return ['text' => '', 'receipt' => '', 'phone' => ''];
        }

        $compactPhone = preg_replace('/[\s\-()+]+/', '', $clean) ?? $clean;
        if (str_starts_with($compactPhone, '00967')) {
            $compactPhone = substr($compactPhone, 5);
        } elseif (str_starts_with($compactPhone, '967') && strlen($compactPhone) > 3) {
            $compactPhone = substr($compactPhone, 3);
        }

        $receipt = ltrim($clean, '#');
        // إذا كان النص عبارة عن رقم هاتف (أرقام/مفتاح دولة/مسافات فقط)، نوحد نص العرض أيضاً
        $isNumericPhoneInput = (bool) preg_match('/^[\d\s\-()+]+$/', $clean);
        $displayText = $isNumericPhoneInput && $compactPhone !== '' ? $compactPhone : $clean;
        $receiptClean = $isNumericPhoneInput && $compactPhone !== '' ? $compactPhone : ($receipt !== '' ? $receipt : $clean);

        return [
            'text'    => $displayText,
            'receipt' => $receiptClean,
            'phone'   => $compactPhone !== '' ? $compactPhone : $clean,
        ];
    }
}
