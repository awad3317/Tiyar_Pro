<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outgoing_parcels', 'quantity')) {
            Schema::table('outgoing_parcels', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('outgoing_parcels', 'quantity')) {
            Schema::table('outgoing_parcels', function (Blueprint $table) {
                $table->unsignedSmallInteger('quantity')->default(1)->after('package_type');
            });
        }
    }
};
