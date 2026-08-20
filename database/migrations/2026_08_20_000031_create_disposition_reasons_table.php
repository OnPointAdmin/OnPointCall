<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('disposition');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'disposition', 'label']);
            $table->index(['company_id', 'disposition', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_reasons');
    }
};
