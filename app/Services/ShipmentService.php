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
        $clientUuid = isset($data['client_uuid']) ? strtolower(trim((string) $data['client_uuid'])) : '';
        if ($clientUuid !== '' && ! $this->isUuid($clientUuid)) {
            throw new DomainException('معرّف العملية المحلي (client_uuid) غير صالح.');
        }

        if ($clientUuid !== '') {
            $existing = Shipment::with(['office:id,name,branch_name', 'parcels.destinationOffice:id,name,branch_name'])
                ->where('office_id', $office->id)
                ->where('client_uuid', $clientUuid)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

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

        return DB::transaction(function () use ($office, $clientUuid, $driverName, $driverPhone, $notes, $dispatchNow, $rawIds): Shipment {
            // قفل سجل المكتب لتوليد رقم كشف رقمي تسلسلي بدون تصادم
            Office::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();

            if ($clientUuid !== '') {
                $existingInTx = Shipment::with(['office:id,name,branch_name', 'parcels.destinationOffice:id,name,branch_name'])
                    ->where('office_id', $office->id)
                    ->where('client_uuid', $clientUuid)
                    ->first();

                if ($existingInTx) {
                    return $existingInTx;
                }

                if (Shipment::query()->where('client_uuid', $clientUuid)->exists()) {
                    throw new DomainException('معرّف العملية المحلي (client_uuid) مستخدم مسبقاً.');
                }
            }

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
                'client_uuid'           => $clientUuid !== '' ? $clientUuid : null,
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

            $pendingParcelsCount = OutgoingParcel::query()
                ->where('shipment_id', $locked->id)
                ->where('status', OutgoingParcelStatus::PENDING->value)
                ->lockForUpdate()
                ->count();

            if ($pendingParcelsCount === 0) {
                throw new DomainException('لا يمكن إرسال شحنة فارغة؛ يجب أن تحتوي الشحنة على طرد واحد على الأقل بحالة (في الانتظار).');
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
     * إلغاء الشحنة قبل استلام أي طرد منها، مع إعادة جميع طرودها إلى حالة (في الانتظار) وفك ارتباطها.
     */
    public function cancel(Shipment $shipment, Office $office): Shipment
    {
        return DB::transaction(function () use ($shipment, $office): Shipment {
            // قفل الطرود المرتبطة بالشحنة أولاً ثم قفل الشحنة (نفس ترتيب القفل في receiveAtDestination لمنع أي Deadlock)
            $lockedParcels = OutgoingParcel::query()
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->get(['id', 'status', 'linked_incoming_parcel_id']);

            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بإلغاء هذا الكشف.');
            }

            if ($locked->status === ShipmentStatus::CANCELLED) {
                throw new DomainException('هذا الكشف ملغي مسبقاً.');
            }

            $hasReceivedParcel = $lockedParcels->contains(function (OutgoingParcel $p): bool {
                return $p->linked_incoming_parcel_id !== null
                    || in_array($p->status, [
                        OutgoingParcelStatus::RECEIVED,
                        OutgoingParcelStatus::DELIVERED,
                    ], true);
            });

            if (
                $hasReceivedParcel
                || in_array($locked->status, [ShipmentStatus::PARTIALLY_RECEIVED, ShipmentStatus::RECEIVED], true)
            ) {
                throw new DomainException('لا يمكن إلغاء الكشف لوجود طرود تم استلامها بالفعل من الفرع المستلم.');
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
     * إخراج طرد واحد من الشحنة وإعادته إلى حالة (في الانتظار) قبل استلامه من الفرع المستلم،
     * مع قفل الصف لمنع التضارب اللحظي وإعادة حساب مجاميع وحالة الشحنة.
     */
    public function detachParcel(Shipment $shipment, OutgoingParcel $parcel, Office $office): OutgoingParcel
    {
        return DB::transaction(function () use ($shipment, $parcel, $office): OutgoingParcel {
            // قفل الطرد أولاً بنفس ترتيب القفل في receiveAtDestination لمنع أي Deadlock أو سباق استلام
            $lockedParcel = OutgoingParcel::query()
                ->whereKey($parcel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedParcel->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بإخراج هذا الطرد من الشحنة.');
            }

            if ((string) $lockedParcel->shipment_id !== (string) $shipment->id) {
                throw new DomainException('هذا الطرد غير مرتبط بهذه الشحنة.');
            }

            if (
                $lockedParcel->linked_incoming_parcel_id !== null
                || $lockedParcel->status === OutgoingParcelStatus::RECEIVED
            ) {
                throw new DomainException('لا يمكن إخراج الطرد لأنه تم استلامه بالفعل من الفرع المستلم.');
            }

            if ($lockedParcel->status === OutgoingParcelStatus::DELIVERED) {
                throw new DomainException('لا يمكن إخراج طرد تم تسليمه بالفعل.');
            }

            if ($lockedParcel->status === OutgoingParcelStatus::CANCELLED) {
                throw new DomainException('لا يمكن إخراج طرد ملغي.');
            }

            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedShipment->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بإدارة هذه الشحنة.');
            }

            if (! in_array($lockedShipment->status, [
                ShipmentStatus::DRAFT,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::PARTIALLY_RECEIVED,
            ], true)) {
                throw new DomainException('لا يمكن إخراج طرد من شحنة مكتملة الاستلام أو ملغاة.');
            }

            $lockedParcel->update([
                'shipment_id'   => null,
                'status'        => OutgoingParcelStatus::PENDING,
                'dispatched_at' => null,
            ]);

            // إعادة حساب عدد الطرود ومجاميع الشحنة ووجهاتها بعد الإخراج
            $remainingParcels = OutgoingParcel::with('destinationOffice:id,name,branch_name')
                ->where('shipment_id', $lockedShipment->id)
                ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
                ->get();

            $totalPaid = 0.0;
            $totalDeferred = 0.0;
            $destinations = [];

            foreach ($remainingParcels as $p) {
                $amount = (float) ($p->cod_amount ?? 0);
                if ($p->payment_type === OutgoingPaymentType::DEFERRED) {
                    $totalDeferred += $amount;
                } else {
                    $totalPaid += $amount;
                }

                $destLabel = $p->destinationLabel();
                if ($destLabel !== '' && ! in_array($destLabel, $destinations, true)) {
                    $destinations[] = $destLabel;
                }
            }

            $lockedShipment->update([
                'parcels_count'         => $remainingParcels->count(),
                'total_paid_amount'     => round($totalPaid, 2),
                'total_deferred_amount' => round($totalDeferred, 2),
                'total_amount'          => round($totalPaid + $totalDeferred, 2),
                'destinations_summary'  => $destinations,
            ]);

            if ($remainingParcels->isNotEmpty()) {
                Shipment::syncStatusById($lockedShipment->id);
            }

            $parcel->refresh();

            return $lockedParcel;
        });
    }

    /**
     * رفض/إلغاء كشف وارد من مكتب خارجي قبل استلام أي طرد منه.
     */
    public function rejectExternalShipment(Shipment $shipment, Office $receivingOffice, ?string $reason = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $receivingOffice, $reason): Shipment {
            // قفل الطرود أولاً ثم الشحنة بنفس ترتيب القفل القياسي في الخدمة لمنع أي تعارض متزامن
            $lockedParcels = OutgoingParcel::query()
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->get();

            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShipment->office_id !== null || $lockedShipment->external_partner_link_id === null) {
                throw new DomainException('لا يمكن رفض الكشوفات الداخلية المرسلة من المكاتب المشتركة.');
            }

            if ($lockedShipment->rejected_at !== null) {
                throw new DomainException('تم رفض هذا الكشف مسبقاً.');
            }

            $directedParcels = $lockedParcels->filter(
                fn (OutgoingParcel $p): bool => (int) $p->destination_office_id === (int) $receivingOffice->id
                    && $p->status !== OutgoingParcelStatus::CANCELLED
            );

            if ($directedParcels->isEmpty()) {
                throw new DomainException('هذا الكشف غير موجّه إلى مكتبكم.');
            }

            $hasAnyReceivedParcel = $lockedParcels->contains(function (OutgoingParcel $p): bool {
                return $p->linked_incoming_parcel_id !== null
                    || in_array($p->status, [OutgoingParcelStatus::RECEIVED, OutgoingParcelStatus::DELIVERED], true);
            });

            if ($hasAnyReceivedParcel) {
                throw new DomainException('لا يمكن رفض الكشف لأنه تم استلام طرد واحد على الأقل منه بالفعل.');
            }

            $cleanReason = $reason !== null ? trim($reason) : null;

            $lockedShipment->update([
                'rejected_at'      => now(),
                'rejection_reason' => $cleanReason !== '' ? $cleanReason : null,
            ]);

            return $lockedShipment->fresh();
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
        if ($shipment->rejected_at !== null) {
            throw new DomainException('لا يمكن استلام طرود من كشف مرفوض.');
        }

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
            ->whereNull('rejected_at')
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
