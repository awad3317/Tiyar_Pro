<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outgoing_parcels', 'payment_type')) {
            Schema::table('outgoing_parcels', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('paid')->after('cod_amount')->index();
            });
        }
    }

    public function down(): void
    
    {
        if (Schema::hasColumn('outgoing_parcels', 'payment_type')) {
            Schema::table('outgoing_parcels', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};
