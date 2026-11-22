<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipments') && ! Schema::hasColumn('shipments', 'client_uuid')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->uuid('client_uuid')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'client_uuid')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropUnique(['client_uuid']);
                $table->dropColumn('client_uuid');
            });
        }
    }
};
