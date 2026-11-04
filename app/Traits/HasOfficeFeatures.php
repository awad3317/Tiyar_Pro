<?php

namespace App\Traits;

use App\Models\Feature;
use App\Models\Office;
use App\Models\OfficeFeature;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasOfficeFeatures
{
    /**
     * كاش داخلي في الذاكرة على مستوى الطلب الواحد (In-Memory Request Cache)
     * لمنع تكرار استعلامات جدول الكاش عند فحص أكثر من ميزة لنفس المكتب في الصفحة الواحدة
     */
    protected static array $runtimeFeaturesCache = [];

    public static function bootHasOfficeFeatures(): void
    {
        static::created(function (Office $office): void {
            $featureIds = Feature::query()->pluck('id');
            if ($featureIds->isNotEmpty()) {
                $now = now();
                $records = $featureIds->map(fn ($featureId) => [
                    'office_id' => $office->id,
                    'feature_id' => $featureId,
                    'is_enabled' => false,
                    'config' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('office_features')->insertOrIgnore($records);
            }

            $office->refreshFeaturesCache();
        });

        static::deleted(function (Office $office): void {
            $office->clearFeaturesCache();
        });
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'office_features', 'office_id', 'feature_id')
            ->using(OfficeFeature::class)
            ->withPivot(['id', 'is_enabled', 'config'])
            ->withTimestamps();
    }

    public function officeFeatures(): HasMany
    {
        return $this->hasMany(OfficeFeature::class, 'office_id');
    }

    public function getFeaturesCacheKey(): string
    {
        return "office_features:{$this->id}";
    }

    public function getFeaturesDictionary(): array
    {
        if ($this->id && isset(static::$runtimeFeaturesCache[$this->id])) {
            return static::$runtimeFeaturesCache[$this->id];
        }

        $dictionary = Cache::rememberForever($this->getFeaturesCacheKey(), function () {
            $features = $this->relationLoaded('features')
                ? $this->features
                : $this->features()->get();

            $dictionary = [];
            foreach ($features as $feature) {
                $config = $feature->pivot?->config;
                if (is_string($config)) {
                    $config = json_decode($config, true);
                }

                $dictionary[$feature->code] = [
                    'is_enabled' => (bool) ($feature->pivot?->is_enabled ?? false),
                    'config' => $config,
                ];
            }

            return $dictionary;
        });

        if ($this->id) {
            static::$runtimeFeaturesCache[$this->id] = $dictionary;
        }

        return $dictionary;
    }

    public function hasFeature(string $code): bool
    {
        $dictionary = $this->getFeaturesDictionary();

        return (bool) ($dictionary[$code]['is_enabled'] ?? false);
    }

    public function getFeatureConfig(string $code, ?string $key = null, mixed $default = null): mixed
    {
        $dictionary = $this->getFeaturesDictionary();
        $config = $dictionary[$code]['config'] ?? null;

        if ($key === null) {
            return $config ?? $default;
        }

        return data_get($config, $key, $default);
    }

    public function clearFeaturesCache(): void
    {
        if ($this->id) {
            unset(static::$runtimeFeaturesCache[$this->id]);
        }
        Cache::forget($this->getFeaturesCacheKey());
        $this->unsetRelation('features');
        $this->unsetRelation('officeFeatures');
    }

    public function refreshFeaturesCache(): array
    {
        $this->clearFeaturesCache();

        return $this->getFeaturesDictionary();
    }

    public static function clearAllOfficesFeaturesCache(): void
    {
        static::$runtimeFeaturesCache = [];
        $officeIds = Office::query()->pluck('id');
        foreach ($officeIds as $id) {
            Cache::forget("office_features:{$id}");
        }
    }

    public function syncFeaturesStatus(array $enabledFeatureIdsOrCodes): void
    {
        DB::transaction(function () use ($enabledFeatureIdsOrCodes) {
            $allFeatures = Feature::all(['id', 'code']);

            $candidates = [];
            foreach ($enabledFeatureIdsOrCodes as $key => $value) {
                if ($value === true || $value === 1 || $value === '1' || $value === 'on') {
                    $candidates[] = $key;
                } else {
                    $candidates[] = $value;
                }
            }

            $enabledIds = [];
            foreach ($allFeatures as $feature) {
                if (in_array($feature->id, $candidates, false) ||
                    in_array((string) $feature->id, $candidates, true) ||
                    in_array($feature->code, $candidates, true)) {
                    $enabledIds[] = $feature->id;
                }
            }

            $now = now();
            $records = $allFeatures->map(fn ($f) => [
                'office_id' => $this->id,
                'feature_id' => $f->id,
                'is_enabled' => false,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('office_features')->insertOrIgnore($records);

            if (!empty($enabledIds)) {
                OfficeFeature::query()
                    ->where('office_id', $this->id)
                    ->whereIn('feature_id', $enabledIds)
                    ->update(['is_enabled' => true, 'updated_at' => $now]);
            }

            OfficeFeature::query()
                ->where('office_id', $this->id)
                ->whereNotIn('feature_id', $enabledIds)
                ->update(['is_enabled' => false, 'updated_at' => $now]);
        });

        $this->refreshFeaturesCache();
    }

    public function toggleFeature(string $code, bool $status, ?array $config = null): bool
    {
        $feature = Feature::query()->where('code', $code)->first();
        if (! $feature) {
            return false;
        }

        $attributes = ['is_enabled' => $status];
        if (func_num_args() >= 3) {
            $attributes['config'] = $config;
        }

        OfficeFeature::query()->updateOrCreate(
            [
                'office_id' => $this->id,
                'feature_id' => $feature->id,
            ],
            $attributes
        );

        $this->refreshFeaturesCache();

        return true;
    }
}
