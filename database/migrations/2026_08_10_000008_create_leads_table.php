<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 10);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('venue')->nullable();
            $table->string('event')->nullable();
            $table->string('external_lead_id')->nullable();
            $table->string('consent_token')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->default('holding');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('next_day_part')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('callback_at')->nullable();
            $table->foreignId('callback_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calling_list_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('partner_list')->nullable();
            $table->unsignedBigInteger('queue_rank')->default(0);
            $table->string('lead_type');
            $table->jsonb('extra_fields')->nullable();
            $table->string('soft_score_code')->nullable();
            $table->string('soft_score_status')->nullable();
            $table->timestamp('soft_score_checked_at')->nullable();
            $table->text('soft_score_last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'phone']);
            $table->unique(['company_id', 'external_lead_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'calling_list_id', 'queue_rank']);
            $table->index(['company_id', 'callback_owner_id', 'callback_at']);
            $table->index(['company_id', 'lead_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
