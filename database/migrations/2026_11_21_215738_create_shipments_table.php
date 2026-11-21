<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->string('shipment_number', 30); // رقم كشف أرقام فقط
            $table->string('driver_name', 120);
            $table->string('driver_phone', 25);
            $table->unsignedInteger('parcels_count')->default(0);
            $table->decimal('total_paid_amount', 12, 2)->default(0);
            $table->decimal('total_deferred_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('destinations_summary')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'shipment_number']);
            $table->index(['office_id', 'status']);
        });

        Schema::table('outgoing_parcels', function (Blueprint $table) {
            $table->foreignUuid('shipment_id')
                ->nullable()
                ->after('office_id')
                ->constrained('shipments')
                ->nullOnDelete();

            $table->index(['shipment_id', 'destination_office_id']);
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_parcels', function (Blueprint $table) {
            $table->dropIndex(['shipment_id', 'destination_office_id']);
            $table->dropConstrainedForeignId('shipment_id');
        });

        Schema::dropIfExists('shipments');
    }
};
