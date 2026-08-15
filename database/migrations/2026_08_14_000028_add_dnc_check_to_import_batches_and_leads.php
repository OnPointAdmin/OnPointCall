<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->boolean('run_dnc_check')->default(false)->after('run_qualification');
            $table->unsignedInteger('dnc_pending')->default(0)->after('qualification_error');
            $table->unsignedInteger('dnc_clear')->default(0)->after('dnc_pending');
            $table->unsignedInteger('dnc_hit')->default(0)->after('dnc_clear');
            $table->unsignedInteger('dnc_invalid')->default(0)->after('dnc_hit');
            $table->unsignedInteger('dnc_error')->default(0)->after('dnc_invalid');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('dnc_status')->nullable()->after('qualification_result');
            $table->timestamp('dnc_checked_at')->nullable()->after('dnc_status');
            $table->text('dnc_last_error')->nullable()->after('dnc_checked_at');
            $table->json('dnc_result')->nullable()->after('dnc_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'run_dnc_check',
                'dnc_pending',
                'dnc_clear',
                'dnc_hit',
                'dnc_invalid',
                'dnc_error',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'dnc_status',
                'dnc_checked_at',
                'dnc_last_error',
                'dnc_result',
            ]);
        });
    }
};
