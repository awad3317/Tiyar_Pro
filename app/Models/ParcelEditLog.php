<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelEditLog extends Model
{
    public const FIELD_LABELS = [
        'recipient_name'     => 'اسم المستلم',
        'recipient_phone'    => 'رقم المستلم',
        'package_type'       => 'نوع الطرد',
        'receipt_number'     => 'رقم السند',
        'source_office_name' => 'المكتب المرسل',
        'payment_type'       => 'نوع الحساب (آجل/محاسب)',
        'cod_amount'         => 'المبلغ المطلوب (آجل)',
        'manifest_id'        => 'كشف السائق',
    ];

    protected $fillable = [
        'parcel_id',
        'office_id',
        'accountant_id',
        'action',
        'changed_fields',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'old_values'     => 'array',
            'new_values'     => 'array',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    public static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return $field === 'manifest_id' ? 'غير مصنف (بدون كشف)' : 'فارغ';
        }

        if ($field === 'manifest_id') {
            return 'كشف رقم #' . $value;
        }

        if ($field === 'payment_type') {
            return $value === 'postpaid' ? 'آجل (عليه مبلغ)' : 'محاسب (خالص)';
        }

        if ($field === 'cod_amount') {
            return number_format((float) $value) . ' ر.ي';
        }

        return (string) $value;
    }
}

