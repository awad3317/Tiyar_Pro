<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'feature_id'], 'uq_office_feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_features');
    }
};

