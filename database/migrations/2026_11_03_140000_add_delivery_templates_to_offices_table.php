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
            if (! Schema::hasColumn('offices', 'delivery_sms_template')) {
                $table->text('delivery_sms_template')->nullable()->after('sms_template');
            }
            if (! Schema::hasColumn('offices', 'delivery_whatsapp_template')) {
                $table->text('delivery_whatsapp_template')->nullable()->after('whatsapp_template');
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
            if (Schema::hasColumn('offices', 'delivery_sms_template')) {
                $columnsToDrop[] = 'delivery_sms_template';
            }
            if (Schema::hasColumn('offices', 'delivery_whatsapp_template')) {
                $columnsToDrop[] = 'delivery_whatsapp_template';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

