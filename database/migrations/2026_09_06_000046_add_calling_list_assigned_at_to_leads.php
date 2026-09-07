<?php

use App\Services\Leads\CallingListAssignedAtBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('calling_list_assigned_at')->nullable()->after('calling_list_id');
        });

        app(CallingListAssignedAtBackfill::class)->run();
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('calling_list_assigned_at');
        });
    }
};
