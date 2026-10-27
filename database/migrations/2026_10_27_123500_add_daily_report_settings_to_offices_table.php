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
        Schema::table('offices', function (Blueprint $table) {
            if (!Schema::hasColumn('offices', 'manager_phone')) {
                $table->string('manager_phone', 30)->nullable()->after('whatsapp_sender_phone');
            }
            if (!Schema::hasColumn('offices', 'send_daily_report')) {
                $table->boolean('send_daily_report')->default(false)->after('manager_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('offices', 'manager_phone')) {
                $columnsToDrop[] = 'manager_phone';
            }
            if (Schema::hasColumn('offices', 'send_daily_report')) {
                $columnsToDrop[] = 'send_daily_report';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
