<?php

namespace App\Services;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutgoingParcelService
{
    public function __construct(
        protected ParcelArrivalNotificationService $arrivalNotificationService
    ) {}

    /**
     * إنشاء طرد صادر جديد للمكتب بحالة pending مع توليد رقم سند تسلسلي خاص بالمكتب.
     */
    public function create(Office $office, array $data): OutgoingParcel
    {
        $rawDestinationId = $data['destination_office_id'] ?? null;
        $destinationOfficeId = ($rawDestinationId !== null && $rawDestinationId !== '')
            ? (int) $rawDestinationId
            : null;

        $externalOfficeName = trim((string) ($data['external_office_name'] ?? ''));
        $externalOfficeName = $externalOfficeName !== '' ? $externalOfficeName : null;

        if ($destinationOfficeId !== null) {
            if ($destinationOfficeId === (int) $office->id) {
                throw new DomainException('لا يمكن إرسال الطرد إلى نفس المكتب.');
            }

            if (! Office::query()->whereKey($destinationOfficeId)->exists()) {
                throw new DomainException('المكتب المستلم المحدد غير موجود.');
            }

            // وجهة داخلية معتمدة
            $externalOfficeName = null;
        } elseif ($externalOfficeName === null) {
            throw new DomainException('يجب تحديد مكتب مستلم داخل النظام أو إدخال اسم مكتب خارجي.');
        }

        if (! isset($data['cod_amount']) || $data['cod_amount'] === '' || ! is_numeric($data['cod_amount']) || (float) $data['cod_amount'] < 0) {
            throw new DomainException('يرجى إدخال المبلغ بشكل صحيح.');
        }
        $codAmount = (float) $data['cod_amount'];

        $rawPaymentType = $data['payment_type'] ?? null;
        $paymentType = $rawPaymentType instanceof OutgoingPaymentType
            ? $rawPaymentType
            : OutgoingPaymentType::tryFrom((string) $rawPaymentType);

        if ($paymentType === null) {
            throw new DomainException('يرجى تحديد حالة الدفع: محاسب أو آجل.');
        }

        $recipientName = trim((string) ($data['recipient_name'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        return DB::transaction(function () use (
            $office,
            $destinationOfficeId,
            $externalOfficeName,
            $codAmount,
            $paymentType,
            $recipientName,
            $notes,
            $data
        ): OutgoingParcel {
            Office::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();

            $lastNumber = (int) OutgoingParcel::where('office_id', $office->id)
                ->selectRaw('MAX(CAST(receipt_number AS UNSIGNED)) as max_num')
                ->value('max_num');

            $nextReceiptNumber = (string) ($lastNumber + 1);

            return OutgoingParcel::create([
                'office_id'             => $office->id,
                'destination_office_id' => $destinationOfficeId,
                'external_office_name'  => $externalOfficeName,
                'sender_name'           => trim((string) ($data['sender_name'] ?? '')),
                'sender_phone'          => trim((string) ($data['sender_phone'] ?? '')),
                'recipient_name'        => $recipientName !== '' ? $recipientName : null,
                'recipient_phone'       => trim((string) ($data['recipient_phone'] ?? '')),
                'package_type'          => trim((string) ($data['package_type'] ?? 'طرد')) ?: 'طرد',
                'receipt_number'        => $nextReceiptNumber,
                'cod_amount'            => $codAmount,
                'payment_type'          => $paymentType,
                'notes'                 => $notes !== '' ? $notes : null,
                'status'                => OutgoingParcelStatus::PENDING,
            ]);
        });
    }

    /**
     * تحويل الطرد الصادر من pending إلى in_transit.
     */
    public function markInTransit(OutgoingParcel $parcel, Office $office): OutgoingParcel
    {
        return DB::transaction(function () use ($parcel, $office): OutgoingParcel {
            $locked = OutgoingParcel::query()
                ->whereKey($parcel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بتعديل حالة هذا الطرد.');
            }

            if ($locked->status !== OutgoingParcelStatus::PENDING) {
                throw new DomainException('يمكن تحويل الطرد إلى (في الطريق) من حالة (في الانتظار) فقط.');
            }

            $locked->update([
                'status'        => OutgoingParcelStatus::IN_TRANSIT,
                'dispatched_at' => now(),
            ]);

            $parcel->refresh();

            return $locked;
        });
    }

    /**
     * تأكيد تسليم طرد صادر موجّه إلى مكتب خارجي.
     */
    public function markExternalDelivered(OutgoingParcel $parcel, Office $office): OutgoingParcel
    {
        return DB::transaction(function () use ($parcel, $office): OutgoingParcel {
            $locked = OutgoingParcel::query()
                ->whereKey($parcel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بتعديل حالة هذا الطرد.');
            }

            if (! $locked->isExternal()) {
                throw new DomainException('هذا الإجراء مخصص للطرود الموجهة إلى مكاتب خارجية فقط.');
            }

            if ($locked->status !== OutgoingParcelStatus::IN_TRANSIT) {
                throw new DomainException('يمكن تأكيد التسليم الخارجي للطرود التي حالتها (في الطريق) فقط.');
            }

            $locked->update([
                'status' => OutgoingParcelStatus::DELIVERED,
            ]);

            $parcel->refresh();

            return $locked;
        });
    }

    /**
     * إلغاء طرد صادر قبل استلامه بالفرع المستلم.
     */
    public function cancel(OutgoingParcel $parcel, Office $office): OutgoingParcel
    {
        return DB::transaction(function () use ($parcel, $office): OutgoingParcel {
            $locked = OutgoingParcel::query()
                ->whereKey($parcel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->office_id !== (int) $office->id) {
                throw new DomainException('غير مصرح لك بإلغاء هذا الطرد.');
            }

            if ($locked->linked_incoming_parcel_id !== null) {
                throw new DomainException('لا يمكن إلغاء طرد تم استلامه وتحويله إلى طرد وارد.');
            }

            if (! in_array($locked->status, [OutgoingParcelStatus::PENDING, OutgoingParcelStatus::IN_TRANSIT], true)) {
                throw new DomainException('يمكن إلغاء الطرد فقط عندما تكون حالته (في الانتظار) أو (في الطريق).');
            }

            $locked->update([
                'status'       => OutgoingParcelStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            $parcel->refresh();

            return $locked;
        });
    }

    /**
     * استلام الطرد الصادر في الفرع المستلم وتحويله تلقائياً إلى طرد وارد (Parcel)
     * مع حماية كاملة ضد الاستلام المزدوج وإطلاق إشعار الوصول بعد اعتماد المعاملة.
     */
    public function receiveAtDestination(OutgoingParcel $outgoingParcel, Office $receivingOffice): Parcel
    {
        $newParcel = DB::transaction(function () use ($outgoingParcel, $receivingOffice): Parcel {
            $locked = OutgoingParcel::with('office')
                ->whereKey($outgoingParcel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->destination_office_id === null || (int) $locked->destination_office_id !== (int) $receivingOffice->id) {
                throw new DomainException('هذا الطرد غير موجّه إلى مكتبكم.');
            }

            if ($locked->linked_incoming_parcel_id !== null) {
                throw new DomainException('تم استلام هذا الطرد مسبقاً ولا يمكن استلامه مرة أخرى.');
            }

            if ($locked->status !== OutgoingParcelStatus::IN_TRANSIT) {
                throw new DomainException('لا يمكن استلام الطرد إلا عندما تكون حالته (في الطريق).');
            }

            $sourceOffice = $locked->office;
            $sourceBranch = trim((string) ($sourceOffice?->branch_name ?? ''));
            $sourceOfficeName = null;
            if ($sourceOffice) {
                $formatted = $sourceBranch !== ''
                    ? "{$sourceOffice->name} - {$sourceBranch}"
                    : $sourceOffice->name;
                $sourceOfficeName = mb_substr($formatted, 0, 100);
            }

            $sourceRegion = $sourceBranch !== '' ? mb_substr($sourceBranch, 0, 100) : null;

            // إذا كان الطرد (آجل - deferred) ينتقل المبلغ لتحصيله عند التسليم، أما إذا كان (محاسب - paid) فيكون null لعدم التحصيل مرتين
            $codAmount = ($locked->payment_type === OutgoingPaymentType::DEFERRED && $locked->cod_amount !== null)
                ? (float) $locked->cod_amount
                : null;

            $parcel = Parcel::create([
                'office_id'          => $receivingOffice->id,
                'recipient_name'     => $locked->recipient_name,
                'recipient_phone'    => $locked->recipient_phone,
                'package_type'       => $locked->package_type,
                'receipt_number'     => $locked->receipt_number,
                'cod_amount'         => $codAmount,
                'source_office_id'   => $locked->office_id,
                'source_office_name' => $sourceOfficeName,
                'source_region'      => $sourceRegion,
                'status'             => ParcelStatus::InOffice,
            ]);

            $locked->update([
                'status'                    => OutgoingParcelStatus::RECEIVED,
                'received_at'               => now(),
                'linked_incoming_parcel_id' => $parcel->id,
            ]);

            return $parcel;
        });

        $outgoingParcel->refresh();

        // إطلاق مسار إشعارات الوصول المعتمد بعد commit المعاملة؛ أي فشل في الإشعار لا يُلغي الاستلام
        try {
            $this->arrivalNotificationService->notifyArrival($newParcel, $receivingOffice, 'both');
        } catch (\Throwable $e) {
            Log::error("OutgoingParcelService::receiveAtDestination arrival notification failed for parcel #{$newParcel->id}: " . $e->getMessage(), [
                'outgoing_parcel_id'  => $outgoingParcel->id,
                'incoming_parcel_id'  => $newParcel->id,
                'receiving_office_id' => $receivingOffice->id,
            ]);
        }

        return $newParcel->fresh() ?? $newParcel;
    }
}
