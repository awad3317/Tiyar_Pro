<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // ربط الطرد بالمكتب المتواجد فيه
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            
            // بيانات المستلم والشحنة
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 25)->index();
            $table->string('package_type');
            $table->string('receipt_number')->nullable()->index();
            
            // المكتب القادم منه (المصدر)
            $table->foreignId('source_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('source_office_name')->nullable();

            // الحالة وتاريخ التسليم
            $table->enum('status', ['in_office', 'delivered', 'returned'])->default('in_office')->index();
            $table->timestamp('delivered_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};