<?php

use App\Models\Feature;
use App\Models\Office;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accountants')) {
            Schema::create('accountants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('phone', 20)->unique();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();

                $table->index(['office_id', 'is_active']);
            });
        }

        // ضمان تسجيل ومزامنة ميزة المحاسبين تلقائياً عند تشغيل migrate على السيرفر الحي
        if (Schema::hasTable('features') && Schema::hasTable('office_features') && Schema::hasTable('offices')) {
            $feature = Feature::query()->updateOrCreate(
                ['code' => 'office_accountants'],
                [
                    'name'        => 'نظام المحاسبين للمكتب',
                    'description' => 'تفعيل تطبيق المحاسبين المستقل لمتابعة الأمور المالية وتحصيل الطرود الخاصة بالمكتب.',
                ]
            );

            $officeIds = Office::query()->pluck('id');
            if ($officeIds->isNotEmpty()) {
                $now = now();
                $records = $officeIds->map(fn ($officeId) => [
                    'office_id'  => $officeId,
                    'feature_id' => $feature->id,
                    'is_enabled' => false,
                    'config'     => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('office_features')->insertOrIgnore($records);
                Office::clearAllOfficesFeaturesCache();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accountants');
    }
};
