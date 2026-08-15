<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch_skipped_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('existing_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('reason');
            $table->string('matched_on')->nullable();
            $table->string('phone', 10)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('external_lead_id')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_skipped_rows');
    }
};
