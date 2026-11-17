<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فهرس مركب لتسريع استعلام وفرز الطرود غير المصنفة حسب المكتب وتاريخ الإنشاء
     */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->index(
                ['office_id', 'manifest_id', 'created_at', 'id'],
                'parcels_office_unclassified_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropIndex('parcels_office_unclassified_sort_idx');
        });
    }
};
