<?php

namespace App\Models;

use App\Enums\OutgoingParcelStatus;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_uuid',
        'office_id',
        'shipment_number',
        'driver_name',
        'driver_phone',
        'parcels_count',
        'total_paid_amount',
        'total_deferred_amount',
        'total_amount',
        'destinations_summary',
        'status',
        'notes',
        'dispatched_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'                => ShipmentStatus::class,
            'parcels_count'         => 'integer',
            'total_paid_amount'     => 'decimal:2',
            'total_deferred_amount' => 'decimal:2',
            'total_amount'          => 'decimal:2',
            'destinations_summary'  => 'array',
            'dispatched_at'         => 'datetime',
            'completed_at'          => 'datetime',
            'cancelled_at'          => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(OutgoingParcel::class, 'shipment_id');
    }

    public function senderOfficeLabel(): string
    {
        $office = $this->office;
        if (! $office) {
            return 'مكتب غير معروف';
        }

        $branch = trim((string) ($office->branch_name ?? ''));

        return $branch !== '' ? "{$office->name} - {$branch}" : $office->name;
    }

    /**
     * تحديث حالة الشحنة تلقائياً بناءً على حالات الطرود الموجودة بداخلها (دعم تعدد الوجهات).
     */
    public static function syncStatusById(?string $shipmentId): void
    {
        if (! $shipmentId) {
            return;
        }

        $shipment = self::query()->whereKey($shipmentId)->lockForUpdate()->first();
        if (! $shipment || in_array($shipment->status, [ShipmentStatus::DRAFT, ShipmentStatus::CANCELLED], true)) {
            return;
        }

        $activeParcels = OutgoingParcel::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', '!=', OutgoingParcelStatus::CANCELLED->value)
            ->get(['status']);

        $totalActive = $activeParcels->count();
        if ($totalActive === 0) {
            return;
        }

        $completedCount = $activeParcels->filter(function (OutgoingParcel $p) {
            return in_array($p->status, [OutgoingParcelStatus::RECEIVED, OutgoingParcelStatus::DELIVERED], true);
        })->count();

        if ($completedCount >= $totalActive) {
            $shipment->update([
                'status'       => ShipmentStatus::RECEIVED,
                'completed_at' => $shipment->completed_at ?? now(),
            ]);
        } elseif ($completedCount > 0) {
            $shipment->update([
                'status'       => ShipmentStatus::PARTIALLY_RECEIVED,
                'completed_at' => null,
            ]);
        } else {
            $shipment->update([
                'status'       => ShipmentStatus::IN_TRANSIT,
                'completed_at' => null,
            ]);
        }
    }
}
