<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blackout_dates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'date']);
        });

        Schema::table('blackout_dates', function (Blueprint $table) {
            $table->string('state_code', 10)->default('ALL')->after('date');
            $table->unique(['company_id', 'date', 'state_code']);
        });
    }

    public function down(): void
    {
        Schema::table('blackout_dates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'date', 'state_code']);
            $table->dropColumn('state_code');
        });

        Schema::table('blackout_dates', function (Blueprint $table) {
            $table->unique(['company_id', 'date']);
        });
    }
};
