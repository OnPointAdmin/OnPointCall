<?php

use App\Services\Dashboard\LegacyDashboardEmailMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('report_type');
            $table->string('period')->nullable();
            $table->json('days_of_week');
            $table->json('send_times');
            $table->string('group_by')->nullable();
            $table->string('last_sent_slot')->nullable();
            $table->timestamps();
        });

        Schema::create('report_schedule_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            $table->unique(['report_schedule_id', 'email']);
        });

        app(LegacyDashboardEmailMigrator::class)->migrate();
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedule_recipients');
        Schema::dropIfExists('report_schedules');
    }
};
