<?php

use App\Models\Company;
use App\Services\Leads\AgentCallbacksProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('booking_id');
            $table->string('salesforce_booking_id', 18)->nullable()->after('notes');
            $table->unique(['company_id', 'salesforce_booking_id']);
        });

        Schema::create('booking_callback_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('days_of_week');
            $table->json('run_times');
            $table->string('last_run_slot')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('booking_callback_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_no_phone_count')->default(0);
            $table->unsignedInteger('skipped_dnc_terminal_count')->default(0);
            $table->unsignedInteger('closed_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'finished_at']);
        });

        Schema::create('booking_callback_sync_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_callback_sync_run_id')->constrained()->cascadeOnDelete();
            $table->string('booking_number')->nullable();
            $table->string('salesforce_booking_id', 18)->nullable();
            $table->string('representative_name')->nullable();
            $table->string('employee_id', 18)->nullable();
            $table->string('reason');
            $table->timestamps();

            $table->index(['booking_callback_sync_run_id', 'reason']);
        });

        Company::query()->orderBy('id')->each(function (Company $company): void {
            app(AgentCallbacksProvisioner::class)->ensure($company->id);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_callback_sync_errors');
        Schema::dropIfExists('booking_callback_sync_runs');
        Schema::dropIfExists('booking_callback_schedules');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'salesforce_booking_id']);
            $table->dropColumn(['notes', 'salesforce_booking_id']);
        });
    }
};
