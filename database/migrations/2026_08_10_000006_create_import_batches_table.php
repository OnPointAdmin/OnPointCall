<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_filename');
            $table->timestamp('imported_at');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->string('lead_type');
            $table->boolean('run_soft_score')->default(false);
            $table->unsignedInteger('soft_score_pending')->default(0);
            $table->unsignedInteger('soft_score_qualified')->default(0);
            $table->unsignedInteger('soft_score_not_qualified')->default(0);
            $table->unsignedInteger('soft_score_error')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
