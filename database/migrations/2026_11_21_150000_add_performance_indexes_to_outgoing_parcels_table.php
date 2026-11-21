<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_parcels', function (Blueprint $table) {
            $table->index(['office_id', 'created_at'], 'outgoing_parcels_office_created_idx');
            $table->index(['office_id', 'status', 'created_at'], 'outgoing_parcels_office_status_created_idx');
            $table->index(['destination_office_id', 'status', 'dispatched_at'], 'outgoing_parcels_dest_status_dispatched_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_parcels', function (Blueprint $table) {
            $table->dropIndex('outgoing_parcels_office_created_idx');
            $table->dropIndex('outgoing_parcels_office_status_created_idx');
            $table->dropIndex('outgoing_parcels_dest_status_dispatched_idx');
        });
    }
};
