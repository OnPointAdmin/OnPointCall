<?php

use App\Models\BookingCallbackSchedule;
use App\Models\Company;
use App\Services\Leads\AgentCallbacksProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_callback_schedules', function (Blueprint $table) {
            $table->foreignId('calling_list_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('booking_callback_sync_runs', function (Blueprint $table) {
            $table->foreignId('calling_list_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();
        });

        Company::query()->orderBy('id')->each(function (Company $company): void {
            $listId = app(AgentCallbacksProvisioner::class)->listFor($company->id)->id;

            BookingCallbackSchedule::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNull('calling_list_id')
                ->update(['calling_list_id' => $listId]);
        });
    }

    public function down(): void
    {
        Schema::table('booking_callback_sync_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calling_list_id');
        });

        Schema::table('booking_callback_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calling_list_id');
        });
    }
};
