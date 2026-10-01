<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم صاحب الرقم أو الفرع (لتمييزه عندك)
            $table->string('instance_id')->unique(); // اسم الـ Instance في السيرفر (مثل awad)
            $table->string('pin_code'); // الرمز السري المخصص له للدخول للواجهة
            $table->string('phone_number')->nullable(); // رقم الهاتف المسجل (اختياري)
            $table->boolean('is_active')->default(true); // تفعيل أو تعطيل الحساب
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};
