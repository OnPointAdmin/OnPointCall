<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->boolean('run_rnd_check')->default(false)->after('run_soft_score');
            $table->unsignedInteger('rnd_pending')->default(0)->after('soft_score_error');
            $table->unsignedInteger('rnd_clear')->default(0)->after('rnd_pending');
            $table->unsignedInteger('rnd_reassigned')->default(0)->after('rnd_clear');
            $table->unsignedInteger('rnd_no_data')->default(0)->after('rnd_reassigned');
            $table->unsignedInteger('rnd_error')->default(0)->after('rnd_no_data');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('rnd_status')->nullable()->after('soft_score_last_error');
            $table->timestamp('rnd_checked_at')->nullable()->after('rnd_status');
            $table->text('rnd_last_error')->nullable()->after('rnd_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'run_rnd_check',
                'rnd_pending',
                'rnd_clear',
                'rnd_reassigned',
                'rnd_no_data',
                'rnd_error',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'rnd_status',
                'rnd_checked_at',
                'rnd_last_error',
            ]);
        });
    }
};
