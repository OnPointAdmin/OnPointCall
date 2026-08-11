<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('state_code', 10);
            $table->time('window_start');
            $table->time('window_end');
            $table->jsonb('permitted_weekdays');
            $table->boolean('manual_dial_only')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'state_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_rules');
    }
};
