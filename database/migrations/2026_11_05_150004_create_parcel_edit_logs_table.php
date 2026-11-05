<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parcel_edit_logs')) {
            Schema::create('parcel_edit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('parcel_id')->constrained('parcels')->cascadeOnDelete();
                $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
                $table->foreignId('accountant_id')->nullable()->constrained('accountants')->nullOnDelete();
                $table->string('action', 50)->default('update');
                $table->json('changed_fields');
                $table->json('old_values');
                $table->json('new_values');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['office_id', 'parcel_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_edit_logs');
    }
};

