<?php

namespace App\Services;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use DomainException;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public function __construct(
        protected OutgoingParcelService $outgoingParcelService
    ) {}

    /**
     * إنشاء كشف شحنة جديد (متعدد الوجهات) برقم كشف رقمي فقط، مع منع التكرار وحساب الآجل/المحاسب/الإجمالي.
     */
    public function create(Office $office, array $data): Shipment
    {
        $driverName = trim((string) ($data['driver_name'] ?? ''));
        $driverPhone = $this->sanitizeYemeniPhone((string) ($data['driver_phone'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $dispatchNow = (bool) ($data['dispatch_now'] ?? true);

        if ($driverName === '') {
            throw new DomainException('يرجى إدخال اسم السائق.');
        }

        if (! preg_match('/^7[01378]\d{7}$/', $driverPhone)) {
            throw new DomainException('رقم جوال السائق غير صحيح (يجب أن يكون 9 أرقام تبدأ بـ 70/71/73/77/78).');
        }

        $rawIds = array_values(array_unique(array_filter(
            array_map(fn($id) => trim((string) $id), (array) ($data['parcel_ids'] ?? []))
        )));

        if (empty($rawIds)) {
            throw new DomainException('يجب مسح أو اختيار طرد واحد على الأقل لإنشاء الشحنة.');
        }

        return DB::transaction(function () use ($office, $driverName, $driverPhone, $notes, $dispatchNow, $rawIds): Shipment {
            // قفل سجل المكتب لتوليد رقم كشف رقمي تسلسلي بدون تصادم
            Office::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();

            $parcels = OutgoingParcel::with('destinationOffice:id,name,branch_name')
                ->where('office_id', $office->id)
                ->whereIn('id', $rawIds)
                ->lockForUpdate()
                ->get();

            if ($parcels->count() !== count($rawIds)) {
                throw new DomainException('بعض الطرود المحددة غير موجودة أو لا تتبع مكتبكم.');
            }

            foreach ($parcels as $parcel) {
                if ($parcel->shipment_id !== null) {
                    throw new DomainException("الطرد رقم سند #{$parcel->receipt_number} مضموم مسبقاً إلى شحنة أخرى.");
                }
                if ($parcel->status !== OutgoingParcelStatus::PENDING) {
                    throw new DomainException("الطرد رقم سند #{$parcel->receipt_number} ليس في حالة (في الانتظار).");
                }
            }

            $shipmentNumber = $this->generateNumericShipmentNumber($office);

            $totalPaid = 0.0;
            $totalDeferred = 0.0;
            $destinations = [];

            foreach ($parcels as $parcel) {
                $amount = (float) ($parcel->cod_amount ?? 0);
                if ($parcel->payment_type === OutgoingPaymentType::DEFERRED) {
                    $totalDeferred += $amount;
                } else {
                    $totalPaid += $amount;
                }

                $destLabel = $parcel->destinationLabel();
                if ($destLabel !== '' && ! in_array($destLabel, $destinations, true)) {
                    $destinations[] = $destLabel;
                }
            }

            $now = now();
            $shipment = Shipment::create([
                'office_id'             => $office->id,
                'shipment_number'       => $shipmentNumber,
                'driver_name'           => $driverName,
                'driver_phone'          => $driverPhone,
                'parcels_count'         => $parcels->count(),
                'total_paid_amount'     => round($totalPaid, 2),
                'total_deferred_amount' => round($totalDeferred, 2),
                'total_amount'          => round($totalPaid + $totalDeferred, 2),
                'destinations_summary'  => $destinations,
                'status'                => $dispatchNow ? ShipmentStatus::IN_TRANSIT : ShipmentStatus::DRAFT,
                'notes'                 => $notes !== '' ? $notes : null,
                'dispatched_at'         => $dispatchNow ? $now : null,
            ]);

            $updatePayload = ['shipment_id' => $shipment->id];
            if ($dispatchNow) {
                $updatePayload['status'] = OutgoingParcelStatus::IN_TRANSIT->value;
                $updatePayload['dispatched_at'] = $now;
            }

            OutgoingParcel::query()
                ->whereIn('id', $rawIds)
                ->update($updatePayload);

            return $shipment->load(['office:id,name,branch_name', 'parcels.destinationOffice:id,name,branch_name']);
        });
    }

    /**
     * شحن مسودة كشف وتحويل جميع طرودها إلى (في الطريق).
     */
    public function dispatch(Shipment $shipment, Office $office): Shipment
    {
        return DB::transaction(function () use ($shipment, $office): Shipment {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بشحن هذا الكشف.');
            }

            if ($locked->status !== ShipmentStatus::DRAFT) {
                throw new DomainException('يمكن شحن الكشف من حالة (قيد التجهيز) فقط.');
            }

            $now = now();
            OutgoingParcel::query()
                ->where('shipment_id', $locked->id)
                ->where('status', OutgoingParcelStatus::PENDING->value)
                ->update([
                    'status'        => OutgoingParcelStatus::IN_TRANSIT->value,
                    'dispatched_at' => $now,
                ]);

            $locked->update([
                'status'        => ShipmentStatus::IN_TRANSIT,
                'dispatched_at' => $now,
            ]);

            return $locked->fresh(['office:id,name,branch_name', 'parcels.destinationOffice:id,name,branch_name']);
        });
    }

    /**
     * إلغاء الشحنة قبل استلام أي طرد منها، مع إعادة طرودها إلى حالة (في الانتظار) وفك ارتباطها.
     */
    public function cancel(Shipment $shipment, Office $office): Shipment
    {
        return DB::transaction(function () use ($shipment, $office): Shipment {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بإلغاء هذا الكشف.');
            }

            if (! in_array($locked->status, [ShipmentStatus::DRAFT, ShipmentStatus::IN_TRANSIT], true)) {
                throw new DomainException('لا يمكن إلغاء شحنة تم البدء في استلام طرودها أو تم إلغاؤها مسبقاً.');
            }

            $hasReceived = OutgoingParcel::query()
                ->where('shipment_id', $locked->id)
                ->where(function ($q) {
                    $q->whereNotNull('linked_incoming_parcel_id')
                        ->orWhereIn('status', [
                            OutgoingParcelStatus::RECEIVED->value,
                            OutgoingParcelStatus::DELIVERED->value,
                        ]);
                })
                ->exists();

            if ($hasReceived) {
                throw new DomainException('لا يمكن إلغاء الشحنة بعد استلام أحد طرودها في الفرع المستلم.');
            }

            OutgoingParcel::query()
                ->where('shipment_id', $locked->id)
                ->whereIn('status', [
                    OutgoingParcelStatus::PENDING->value,
                    OutgoingParcelStatus::IN_TRANSIT->value,
                ])
                ->update([
                    'shipment_id'   => null,
                    'status'        => OutgoingParcelStatus::PENDING->value,
                    'dispatched_at' => null,
                ]);

            $locked->update([
                'status'       => ShipmentStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * البحث عن طرد بالباركود (UUID أو رقم السند) لضمّه أثناء إنشاء الشحنة.
     */
    public function findParcelForSendingScan(Office $office, string $rawCode): OutgoingParcel
    {
        $code = $this->extractScanCode($rawCode);
        if ($code === '') {
            throw new DomainException('رمز الباركود فارغ أو غير صالح.');
        }

        $query = OutgoingParcel::with('destinationOffice:id,name,branch_name')
            ->where('office_id', $office->id);

        if ($this->isUuid($code)) {
            $parcel = (clone $query)->where('id', $code)->first();
        } else {
            $cleanReceipt = ltrim($code, '#');
            $parcel = (clone $query)->where('receipt_number', $cleanReceipt)->latest('created_at')->first();
        }

        if (! $parcel) {
            throw new DomainException('لم يتم العثور على طرد صادر بهذا الرمز في مكتبكم.');
        }

        if ($parcel->shipment_id !== null) {
            throw new DomainException("الطرد (سند #{$parcel->receipt_number}) مضموم مسبقاً إلى شحنة أخرى.");
        }

        if ($parcel->status !== OutgoingParcelStatus::PENDING) {
            throw new DomainException("الطرد (سند #{$parcel->receipt_number}) حالته الحالية ({$parcel->status->label()}) ولا يمكن ضمه.");
        }

        return $parcel;
    }

    /**
     * استلام طرد واحد بالباركود داخل شحنة قادمة للفرع المستلم مع اختيار قناة الإشعار.
     */
    public function receiveParcelByScan(
        Shipment $shipment,
        Office $receivingOffice,
        string $rawCode,
        string $channel = 'both',
        bool $afterResponse = true
    ): array {
        $code = $this->extractScanCode($rawCode);
        if ($code === '') {
            throw new DomainException('رمز الباركود فارغ أو غير صالح.');
        }

        $query = OutgoingParcel::query()->where('shipment_id', $shipment->id);

        if ($this->isUuid($code)) {
            $parcel = (clone $query)->where('id', $code)->first();
        } else {
            $cleanReceipt = ltrim($code, '#');
            $parcel = (clone $query)
                ->where('destination_office_id', $receivingOffice->id)
                ->where('receipt_number', $cleanReceipt)
                ->first();
        }

        if (! $parcel) {
            throw new DomainException('هذا الطرد لا ينتمي لهذه الشحنة! يرجى التحقق من الملصق.');
        }

        if ((int) $parcel->destination_office_id !== (int) $receivingOffice->id) {
            throw new DomainException('تنبيه: هذا الطرد موجّه إلى فرع آخر وليس إلى مكتبكم.');
        }

        if ($parcel->linked_incoming_parcel_id !== null || $parcel->status === OutgoingParcelStatus::RECEIVED) {
            throw new DomainException("تم مسح واستلام هذا الطرد مسبقاً (سند #{$parcel->receipt_number}).");
        }

        $incomingParcel = $this->outgoingParcelService->receiveAtDestination(
            $parcel,
            $receivingOffice,
            $afterResponse,
            $channel
        );

        $parcel->refresh();

        $remainingInShipment = OutgoingParcel::query()
            ->where('shipment_id', $shipment->id)
            ->where('destination_office_id', $receivingOffice->id)
            ->where('status', OutgoingParcelStatus::IN_TRANSIT->value)
            ->count();

        $pendingShipmentsCount = self::countPendingReceivedShipments($receivingOffice);

        return [
            'outgoing_parcel'         => $parcel,
            'incoming_parcel_id'      => $incomingParcel->id,
            'remaining_in_shipment'   => $remainingInShipment,
            'pending_shipments_count' => $pendingShipmentsCount,
            'shipment_status'         => $shipment->fresh()->status->value,
        ];
    }

    /**
     * عدد الشحنات القادمة التي ما زال فيها طرود غير مستلمة لهذا المكتب (لشارة الإشعار في الـ bottom-nav).
     */
    public static function countPendingReceivedShipments(Office $office): int
    {
        return Shipment::query()
            ->whereIn('status', [ShipmentStatus::IN_TRANSIT->value, ShipmentStatus::PARTIALLY_RECEIVED->value])
            ->whereHas('parcels', function ($q) use ($office) {
                $q->where('destination_office_id', $office->id)
                    ->where('status', OutgoingParcelStatus::IN_TRANSIT->value);
            })
            ->count();
    }

    /**
     * توليد رقم كشف أرقام فقط بصيغة YYMMDD + تسلسل يومي (مثال: 261121001).
     */
    protected function generateNumericShipmentNumber(Office $office): string
    {
        $prefix = now()->format('ymd');
        $base = (int) ($prefix . '000');

        $lastNumber = (int) Shipment::query()
            ->where('office_id', $office->id)
            ->whereRaw('CAST(shipment_number AS UNSIGNED) > ?', [$base])
            ->whereRaw('CAST(shipment_number AS UNSIGNED) < ?', [$base + 999])
            ->selectRaw('MAX(CAST(shipment_number AS UNSIGNED)) as max_num')
            ->value('max_num');

        return (string) ($lastNumber > 0 ? ($lastNumber + 1) : ($base + 1));
    }

    protected function extractScanCode(string $raw): string
    {
        $clean = trim($raw);
        // دعم الأرقام العربية/الفارسية
        $clean = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $clean
        );

        // إذا كان الكود رابطاً أو نصاً يحتوي على UUID نستخرج الـ UUID مباشرة
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $clean, $m)) {
            return strtolower($m[0]);
        }

        return $clean;
    }

    protected function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    protected function sanitizeYemeniPhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $raw
        ));

        if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
            $digits = substr($digits, 5);
        }

        return substr($digits, 0, 9);
    }
}
