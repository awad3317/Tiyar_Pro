<?php

namespace App\Models;

use App\Enums\ParcelStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcel extends Model
{
    use HasUuids;

    protected $fillable = [
        'office_id',
        'manifest_id',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
        'cod_amount',
        'payment_type',
        'source_office_id',
        'source_office_name',
        'status',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ParcelStatus::class,
            'delivered_at' => 'datetime',
            'cod_amount'   => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Parcel $parcel): void {
            if (!$parcel->exists) {
                if ((float) ($parcel->cod_amount ?? 0) > 0 && (!$parcel->isDirty('payment_type') || empty($parcel->payment_type))) {
                    $parcel->payment_type = 'postpaid';
                } elseif (empty($parcel->payment_type)) {
                    $parcel->payment_type = 'prepaid';
                }
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_id');
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(ParcelEditLog::class, 'parcel_id')->latest();
    }

    /**
     * نقل الطرد لحالة جديدة مع احترام قواعد الانتقال.
     * يعيد false إذا كان الانتقال غير مسموح، و true إذا نجح أو كانت الحالة نفسها.
     */
    public function transitionTo(ParcelStatus $next, ?CarbonInterface $deliveredAt = null): bool
    {
        if ($this->status === $next) {
            return true;
        }

        if (! $this->status->canTransitionTo($next)) {
            return false;
        }

        $this->status = $next;
        $this->delivered_at = $next === ParcelStatus::Delivered ? ($deliveredAt ?? now()) : null;

        return $this->save();
    }
}
