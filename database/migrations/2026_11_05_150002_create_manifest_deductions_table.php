<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('manifest_deductions')) {
            Schema::create('manifest_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manifest_id')->constrained('manifests')->cascadeOnDelete();
                $table->enum('deduction_type', ['workers_unloading', 'office_commission', 'other'])->default('other');
                $table->string('title', 150);
                $table->decimal('amount', 12, 2);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index('manifest_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_deductions');
    }
};

