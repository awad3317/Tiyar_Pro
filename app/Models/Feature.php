<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Feature extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
    ];

    protected static function booted(): void
    {
        static::created(function (Feature $feature): void {
            $officeIds = Office::query()->pluck('id');
            if ($officeIds->isEmpty()) {
                return;
            }

            $now = now();
            $records = $officeIds->map(fn ($officeId) => [
                'office_id' => $officeId,
                'feature_id' => $feature->id,
                'is_enabled' => false,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('office_features')->insertOrIgnore($records);
        });
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'office_features', 'feature_id', 'office_id')
            ->using(OfficeFeature::class)
            ->withPivot(['id', 'is_enabled', 'config'])
            ->withTimestamps();
    }

    public function officeFeatures(): HasMany
    {
        return $this->hasMany(OfficeFeature::class, 'feature_id');
    }
}

