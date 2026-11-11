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
            if (! Schema::hasColumn('parcels', 'sms_status')) {
                $table->string('sms_status', 30)->nullable()->default(null)->after('status');
                $table->index(['office_id', 'sms_status', 'created_at'], 'parcels_sms_status_idx');
            }
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
            if (Schema::hasColumn('parcels', 'sms_status')) {
                $table->dropIndex('parcels_sms_status_idx');
                $table->dropColumn('sms_status');
            }
        });
    }
};

