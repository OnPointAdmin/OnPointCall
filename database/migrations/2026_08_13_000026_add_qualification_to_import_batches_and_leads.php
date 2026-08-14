<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->boolean('run_qualification')->default(false)->after('run_rnd_check');
            $table->unsignedInteger('qualification_pending')->default(0)->after('rnd_error');
            $table->unsignedInteger('qualification_qualified')->default(0)->after('qualification_pending');
            $table->unsignedInteger('qualification_not_qualified')->default(0)->after('qualification_qualified');
            $table->unsignedInteger('qualification_error')->default(0)->after('qualification_not_qualified');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('qualification_status')->nullable()->after('rnd_last_error');
            $table->timestamp('qualification_checked_at')->nullable()->after('qualification_status');
            $table->text('qualification_last_error')->nullable()->after('qualification_checked_at');
            $table->json('qualification_result')->nullable()->after('qualification_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'run_qualification',
                'qualification_pending',
                'qualification_qualified',
                'qualification_not_qualified',
                'qualification_error',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'qualification_status',
                'qualification_checked_at',
                'qualification_last_error',
                'qualification_result',
            ]);
        });
    }
};
