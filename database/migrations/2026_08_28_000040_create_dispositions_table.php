<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('outcome');
            $table->boolean('increments_attempt')->default(true);
            $table->boolean('requires_reason')->default(false);
            $table->string('button_group');
            $table->string('color');
            $table->string('report_group');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositions');
    }
};
