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
            if (!Schema::hasColumn('parcels', 'manifest_id')) {
                $table->foreignId('manifest_id')
                    ->nullable()
                    ->after('office_id')
                    ->constrained('manifests')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('parcels', 'payment_type')) {
                $table->enum('payment_type', ['prepaid', 'postpaid'])
                    ->default('prepaid')
                    ->after('cod_amount');
            }

            $table->index(['office_id', 'manifest_id', 'payment_type'], 'parcels_office_manifest_payment_idx');
        });

        DB::table('parcels')
            ->where('cod_amount', '>', 0)
            ->update(['payment_type' => 'postpaid']);
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropIndex('parcels_office_manifest_payment_idx');

            if (Schema::hasColumn('parcels', 'manifest_id')) {
                $table->dropConstrainedForeignId('manifest_id');
            }

            if (Schema::hasColumn('parcels', 'payment_type')) {
                $table->dropColumn('payment_type');
            }
        });
    }
};

