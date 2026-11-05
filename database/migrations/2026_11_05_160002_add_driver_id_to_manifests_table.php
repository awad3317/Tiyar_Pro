<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            if (!Schema::hasColumn('manifests', 'driver_id')) {
                $table->foreignId('driver_id')
                    ->nullable()
                    ->after('accountant_id')
                    ->constrained('drivers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            if (Schema::hasColumn('manifests', 'driver_id')) {
                $table->dropConstrainedForeignId('driver_id');
            }
        });
    }
};

