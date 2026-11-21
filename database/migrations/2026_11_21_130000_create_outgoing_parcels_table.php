<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->foreignId('destination_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('external_office_name')->nullable();
            $table->string('sender_name');
            $table->string('sender_phone', 25)->index();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 25)->index();
            $table->string('package_type');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('receipt_number')->nullable()->index();
            $table->decimal('cod_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignUuid('linked_incoming_parcel_id')->nullable()->unique()->constrained('parcels')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'status']);
            $table->index(['destination_office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_parcels');
    }
};
