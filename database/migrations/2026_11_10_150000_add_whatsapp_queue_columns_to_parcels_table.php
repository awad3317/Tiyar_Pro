<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            if (! Schema::hasColumn('parcels', 'whatsapp_status')) {
                $table->enum('whatsapp_status', ['pending', 'sent', 'failed'])
                    ->default('pending')
                    ->after('delivered_at');
            }
            if (! Schema::hasColumn('parcels', 'whatsapp_scheduled_at')) {
                $table->timestamp('whatsapp_scheduled_at')->nullable()->after('whatsapp_status');
            }
            if (! Schema::hasColumn('parcels', 'whatsapp_sent_at')) {
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_scheduled_at');
            }

            // فهرس استعلام الكرون كل دقيقة: الطرود المعلقة المستحقة لكل مكتب
            $table->index(['whatsapp_status', 'whatsapp_scheduled_at', 'office_id'], 'parcels_wa_queue_idx');
        });

        // جميع الطرود الموجودة قبل تفعيل الطابور تعتبر منتهية حتى لا يرسلها الكرون بعد الرفع
        DB::table('parcels')->update(['whatsapp_status' => 'sent']);
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropIndex('parcels_wa_queue_idx');
            $table->dropColumn(['whatsapp_status', 'whatsapp_scheduled_at', 'whatsapp_sent_at']);
        });
    }
};

