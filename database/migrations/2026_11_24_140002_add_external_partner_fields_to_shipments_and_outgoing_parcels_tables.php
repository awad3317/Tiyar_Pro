<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->change();

            if (! Schema::hasColumn('shipments', 'external_partner_link_id')) {
                $table->foreignId('external_partner_link_id')
                    ->nullable()
                    ->after('office_id')
                    ->constrained('external_partner_links')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('shipments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)
                    ->nullable()
                    ->unique()
                    ->after('external_partner_link_id');
            }

            if (! Schema::hasColumn('shipments', 'rejected_at')) {
                $table->timestamp('rejected_at')
                    ->nullable()
                    ->after('cancelled_at');
            }

            if (! Schema::hasColumn('shipments', 'rejection_reason')) {
                $table->string('rejection_reason', 255)
                    ->nullable()
                    ->after('rejected_at');
            }
        });

        Schema::table('outgoing_parcels', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->change();

            if (! Schema::hasColumn('outgoing_parcels', 'external_partner_link_id')) {
                $table->foreignId('external_partner_link_id')
                    ->nullable()
                    ->after('office_id')
                    ->constrained('external_partner_links')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_parcels', function (Blueprint $table) {
            if (Schema::hasColumn('outgoing_parcels', 'external_partner_link_id')) {
                $table->dropConstrainedForeignId('external_partner_link_id');
            }
            $table->unsignedBigInteger('office_id')->nullable(false)->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('shipments', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('shipments', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('shipments', 'external_partner_link_id')) {
                $table->dropConstrainedForeignId('external_partner_link_id');
            }
            $table->unsignedBigInteger('office_id')->nullable(false)->change();
        });
    }
};
