<?php

namespace App\Models;

use App\Enums\ParcelStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parcel extends Model
{
    use HasUuids;

    protected $fillable = [
        'office_id',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
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
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
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