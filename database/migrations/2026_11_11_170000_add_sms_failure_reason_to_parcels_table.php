<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            if (! Schema::hasColumn('parcels', 'sms_failure_reason')) {
                $table->string('sms_failure_reason', 255)->nullable()->default(null)->after('sms_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            if (Schema::hasColumn('parcels', 'sms_failure_reason')) {
                $table->dropColumn('sms_failure_reason');
            }
        });
    }
};

