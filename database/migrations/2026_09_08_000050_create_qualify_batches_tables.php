<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualify_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('lead_count')->default(0);
            $table->json('filter')->nullable();
            $table->boolean('run_soft_score')->default(false);
            $table->boolean('run_rnd_check')->default(false);
            $table->boolean('run_qualification')->default(false);
            $table->boolean('run_dnc_check')->default(false);
            $table->unsignedInteger('soft_score_pending')->default(0);
            $table->unsignedInteger('soft_score_qualified')->default(0);
            $table->unsignedInteger('soft_score_not_qualified')->default(0);
            $table->unsignedInteger('soft_score_error')->default(0);
            $table->unsignedInteger('rnd_pending')->default(0);
            $table->unsignedInteger('rnd_clear')->default(0);
            $table->unsignedInteger('rnd_reassigned')->default(0);
            $table->unsignedInteger('rnd_no_data')->default(0);
            $table->unsignedInteger('rnd_error')->default(0);
            $table->unsignedInteger('qualification_pending')->default(0);
            $table->unsignedInteger('qualification_qualified')->default(0);
            $table->unsignedInteger('qualification_not_qualified')->default(0);
            $table->unsignedInteger('qualification_error')->default(0);
            $table->unsignedInteger('dnc_pending')->default(0);
            $table->unsignedInteger('dnc_clear')->default(0);
            $table->unsignedInteger('dnc_hit')->default(0);
            $table->unsignedInteger('dnc_invalid')->default(0);
            $table->unsignedInteger('dnc_error')->default(0);
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('qualify_batch_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qualify_batch_id')->constrained('qualify_batches')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['qualify_batch_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualify_batch_leads');
        Schema::dropIfExists('qualify_batches');
    }
};
