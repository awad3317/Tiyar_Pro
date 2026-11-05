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
            if (empty($manifest->manifest_date)) {
                $manifest->manifest_date = now()->toDateString();
            }

            if (empty($manifest->status)) {
                $manifest->status = 'open';
            }

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
     * مع حساب أعداد الطرود (الإجمالي، الآجل، المحاسب) في استعلام واحد فقط
     */
    public function recalculateTotals(): void
    {
        $parcelStats = $this->parcels()
            ->where('office_id', $this->office_id)
            ->selectRaw("
                COUNT(*) as total_count,
                COALESCE(SUM(CASE WHEN payment_type = 'postpaid' THEN 1 ELSE 0 END), 0) as postpaid_count,
                COALESCE(SUM(CASE WHEN payment_type = 'postpaid' THEN COALESCE(cod_amount, 0) ELSE 0 END), 0) as postpaid_sum
            ")
            ->first();

        $totalCount    = (int) ($parcelStats->total_count ?? 0);
        $postpaidCount = (int) ($parcelStats->postpaid_count ?? 0);
        $totalPostpaid = round((float) ($parcelStats->postpaid_sum ?? 0), 2);

        $totalDeductions = round((float) $this->deductions()->sum('amount'), 2);
        $netDriverAmount = round($totalPostpaid - $totalDeductions, 2);

        // حفظ أعداد الطرود في الذاكرة ومزامنتها كقيم أصلية حتى لا يعتبرها Eloquent أعمدة معدلة عند الحفظ
        $this->setAttribute('parcels_count', $totalCount);
        $this->setAttribute('postpaid_parcels_count', $postpaidCount);
        $this->setAttribute('prepaid_parcels_count', $totalCount - $postpaidCount);
        $this->syncOriginalAttributes([
            'parcels_count',
            'postpaid_parcels_count',
            'prepaid_parcels_count',
        ]);

        // تنفيذ استعلام UPDATE فقط إذا تغيرت المجاميع المالية فعلياً
        if (
            round((float) $this->total_postpaid_amount, 2) !== $totalPostpaid ||
            round((float) $this->total_deductions, 2) !== $totalDeductions ||
            round((float) $this->net_driver_amount, 2) !== $netDriverAmount
        ) {
            self::query()
                ->whereKey($this->id)
                ->update([
                    'total_postpaid_amount' => $totalPostpaid,
                    'total_deductions'      => $totalDeductions,
                    'net_driver_amount'     => $netDriverAmount,
                    'updated_at'            => now(),
                ]);

            $this->total_postpaid_amount = $totalPostpaid;
            $this->total_deductions      = $totalDeductions;
            $this->net_driver_amount     = $netDriverAmount;
        }
    }
}
