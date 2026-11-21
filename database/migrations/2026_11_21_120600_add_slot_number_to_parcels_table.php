<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            if (!Schema::hasColumn('parcels', 'slot_number')) {
                $table->string('slot_number', 50)->nullable()->after('receipt_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            if (Schema::hasColumn('parcels', 'slot_number')) {
                $table->dropColumn('slot_number');
            }
        });
    }
};
