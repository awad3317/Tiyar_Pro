<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('office_partner_links')) {
            Schema::create('office_partner_links', function (Blueprint $table) {
                $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
                $table->foreignId('partner_office_id')->constrained('offices')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['office_id', 'partner_office_id']);
                $table->index('partner_office_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_partner_links');
    }
};
