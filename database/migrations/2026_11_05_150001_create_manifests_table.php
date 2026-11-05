<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('manifests')) {
            Schema::create('manifests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
                $table->foreignId('accountant_id')->nullable()->constrained('accountants')->nullOnDelete();
                $table->string('driver_name', 100);
                $table->string('driver_phone', 20)->nullable();
                $table->string('source_office_name', 100)->nullable();
                $table->string('source_branch_name', 100)->nullable();
                $table->string('manifest_number', 50)->nullable();
                $table->date('manifest_date');
                $table->decimal('total_postpaid_amount', 12, 2)->default(0);
                $table->decimal('total_deductions', 12, 2)->default(0);
                $table->decimal('net_driver_amount', 12, 2)->default(0);
                $table->enum('status', ['open', 'settled'])->default('open');
                $table->timestamp('settled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['office_id', 'status', 'manifest_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manifests');
    }
};

