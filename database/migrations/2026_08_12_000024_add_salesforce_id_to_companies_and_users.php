<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('salesforce_id', 18)->nullable()->unique()->after('active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('salesforce_id', 18)->nullable()->unique()->after('microsoft_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['salesforce_id']);
            $table->dropColumn('salesforce_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['salesforce_id']);
            $table->dropColumn('salesforce_id');
        });
    }
};
