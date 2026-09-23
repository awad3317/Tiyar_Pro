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
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');           
            $table->string('branch_name')->nullable();           // اسم المكتب/الفرع
            $table->string('whatsapp_sender_phone');     // رقم الموظف/المكتب في الواتساب
            $table->string('httpsms_from_phone');        // رقم الشريحة المسجلة في البوابة (+967...)
            $table->string('httpsms_api_key');     
            $table->text('sms_template')->nullable();      // الـ API Key الخاص بالمكتب في httpSMS
            $table->boolean('is_active')->default(true); // حالة التفعيل
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
