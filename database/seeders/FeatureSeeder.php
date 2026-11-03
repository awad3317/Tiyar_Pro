<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $featuresData = [
            [
                'code' => 'vip_customers_tab',
                'name' => 'تبويب العملاء المميزين',
                'category' => 'ui_tab',
                'description' => 'إظهار تبويب خاص بالعملاء المميزين في واجهة الطرود الخاصة بالمكتب',
            ],
            [
                'code' => 'sms_on_delivery',
                'name' => 'إرسال SMS عند تسليم الطرد',
                'category' => 'notification',
                'description' => 'إرسال رسالة نصية قصيرة (SMS) تلقائياً إلى هاتف العميل فور تغيير حالة الطرد إلى تم التسليم',
            ],
            [
                'code' => 'whatsapp_on_delivery',
                'name' => 'إرسال واتساب عند تسليم الطرد',
                'category' => 'notification',
                'description' => 'إرسال رسالة واتساب تلقائياً إلى هاتف العميل فور تغيير حالة الطرد إلى تم التسليم',
            ],
        ];

        $features = collect();
        foreach ($featuresData as $item) {
            $feature = Feature::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                ]
            );
            $features->push($feature);
        }

        $officeIds = Office::query()->pluck('id');
        if ($officeIds->isEmpty()) {
            return;
        }

        $now = now();
        $pivotRows = [];
        foreach ($officeIds as $officeId) {
            foreach ($features as $feature) {
                $pivotRows[] = [
                    'office_id' => $officeId,
                    'feature_id' => $feature->id,
                    'is_enabled' => false,
                    'config' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($pivotRows)) {
            DB::table('office_features')->insertOrIgnore($pivotRows);
        }

        Office::clearAllOfficesFeaturesCache();
    }
}

