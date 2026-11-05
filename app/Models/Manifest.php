<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manifest extends Model
{
    protected $fillable = [
        'office_id',
        'accountant_id',
        'driver_id',
        'driver_name',
        'driver_phone',
        'source_office_name',
        'source_branch_name',
        'manifest_number',
        'manifest_date',
        'total_postpaid_amount',
        'total_deductions',
        'net_driver_amount',
        'status',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manifest_date'         => 'date',
            'settled_at'            => 'datetime',
            'total_postpaid_amount' => 'float',
            'total_deductions'      => 'float',
            'net_driver_amount'     => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Manifest $manifest): void {
            if (
                $manifest->office_id &&
                (!empty($manifest->driver_name) || !empty($manifest->driver_id)) &&
                (!$manifest->exists || $manifest->isDirty(['driver_id', 'driver_name', 'driver_phone']) || empty($manifest->driver_id))
            ) {
                $driver = Driver::resolveForOffice(
                    (int) $manifest->office_id,
                    (string) ($manifest->driver_name ?? ''),
                    $manifest->driver_phone,
                    $manifest->driver_id ? (int) $manifest->driver_id : null
                );

                $manifest->driver_id    = $driver->id;
                $manifest->driver_name  = $driver->name;
                $manifest->driver_phone = $driver->phone;
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'manifest_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(ManifestDeduction::class, 'manifest_id');
    }

    /**
     * إعادة حساب مجاميع الكشف (مجموع الآجل، مجموع الخصومات، الصافي المستحق للسائق)
     */
    public function recalculateTotals(): void
    {
        $totalPostpaid = (float) $this->parcels()
            ->where('office_id', $this->office_id)
            ->where('payment_type', 'postpaid')
            ->sum('cod_amount');

        $totalDeductions = (float) $this->deductions()->sum('amount');

        $this->update([
            'total_postpaid_amount' => round($totalPostpaid, 2),
            'total_deductions'      => round($totalDeductions, 2),
            'net_driver_amount'     => round($totalPostpaid - $totalDeductions, 2),
        ]);
    }
}
