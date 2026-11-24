<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_partner_links')) {
            return;
        }

        Schema::create('external_partner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->string('partner_name', 150);
            $table->string('partner_city', 100)->nullable();
            $table->string('partner_phone', 20)->nullable();
            $table->string('token', 64)->unique();
            $table->string('access_pin', 10)->nullable();
            $table->string('receipt_prefix', 12);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('shipments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['office_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_partner_links');
    }
};
