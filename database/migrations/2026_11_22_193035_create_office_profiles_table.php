<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->unique()->constrained('offices')->cascadeOnDelete();

            // الهوية البصرية والألوان
            $table->string('logo_path')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('primary_color', 20)->default('#041627');
            $table->string('secondary_color', 20)->default('#fe9d20');

            // بيانات الفرع والموقع
            $table->string('tagline_primary', 150)->nullable();
            $table->string('tagline_secondary', 150)->nullable();
            $table->string('branch_address', 255)->nullable();

            // أرقام التواصل
            $table->string('complaints_phone', 60)->nullable();
            $table->string('dispatch_phone_1', 100)->nullable();
            $table->string('dispatch_phone_2', 100)->nullable();
            $table->string('delivery_phone', 60)->nullable();

            // إعدادات السندات والشروط والأحكام
            $table->string('receipt_title', 80)->nullable();
            $table->string('slogan', 150)->nullable();
            $table->json('receipt_terms')->nullable();
            $table->string('footer_text', 120)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_profiles');
    }
};
