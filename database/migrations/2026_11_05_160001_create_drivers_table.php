<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('phone', 20)->nullable();
                $table->timestamps();

                $table->index(['office_id', 'name']);
                $table->index(['office_id', 'phone']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

