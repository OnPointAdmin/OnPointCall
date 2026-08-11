<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calling_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('lead_type');
            $table->jsonb('cadence')->default('{}');
            $table->unsignedSmallInteger('max_attempts_override')->nullable();
            $table->boolean('active')->default(true);
            $table->text('booking_url_template')->nullable();
            $table->jsonb('booking_param_map')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'lead_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calling_lists');
    }
};
