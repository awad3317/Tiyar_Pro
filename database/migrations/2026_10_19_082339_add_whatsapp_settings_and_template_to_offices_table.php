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
            if (!Schema::hasColumn('offices', 'whatsapp_base_url')) {
                $table->string('whatsapp_base_url')->nullable()->after('whatsapp_sender_phone');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_instance_id')) {
                $table->string('whatsapp_instance_id')->nullable()->after('whatsapp_base_url');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_instance_name')) {
                $table->string('whatsapp_instance_name')->nullable()->after('whatsapp_instance_id');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_api_key')) {
                $table->text('whatsapp_api_key')->nullable()->after('whatsapp_instance_name');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_webhook_url')) {
                $table->string('whatsapp_webhook_url')->nullable()->after('whatsapp_api_key');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_timeout')) {
                $table->unsignedInteger('whatsapp_timeout')->nullable()->default(30)->after('whatsapp_webhook_url');
            }
            if (!Schema::hasColumn('offices', 'whatsapp_template')) {
                $table->text('whatsapp_template')->nullable()->after('sms_template');
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

            $columns = [
                'whatsapp_base_url',
                'whatsapp_instance_id',
                'whatsapp_instance_name',
                'whatsapp_api_key',
                'whatsapp_webhook_url',
                'whatsapp_timeout',
                'whatsapp_template',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('offices', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
