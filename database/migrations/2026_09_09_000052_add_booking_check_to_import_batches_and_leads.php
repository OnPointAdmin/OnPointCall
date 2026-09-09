<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->boolean('exclude_future_bookings')->default(true)->after('ignore_national_dnc');
            $table->boolean('exclude_past_bookings')->default(true)->after('exclude_future_bookings');
            $table->unsignedInteger('booking_check_pending')->default(0)->after('dnc_error');
            $table->unsignedInteger('booking_check_clear')->default(0)->after('booking_check_pending');
            $table->unsignedInteger('booking_future_hit')->default(0)->after('booking_check_clear');
            $table->unsignedInteger('booking_past_hit')->default(0)->after('booking_future_hit');
            $table->unsignedInteger('booking_check_error')->default(0)->after('booking_past_hit');
        });

        Schema::table('qualify_batches', function (Blueprint $table) {
            $table->boolean('exclude_future_bookings')->default(true)->after('run_dnc_check');
            $table->boolean('exclude_past_bookings')->default(true)->after('exclude_future_bookings');
            $table->unsignedInteger('booking_check_pending')->default(0)->after('dnc_error');
            $table->unsignedInteger('booking_check_clear')->default(0)->after('booking_check_pending');
            $table->unsignedInteger('booking_future_hit')->default(0)->after('booking_check_clear');
            $table->unsignedInteger('booking_past_hit')->default(0)->after('booking_future_hit');
            $table->unsignedInteger('booking_check_error')->default(0)->after('booking_past_hit');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('booking_check_status')->nullable()->after('dnc_result');
            $table->timestamp('booking_checked_at')->nullable()->after('booking_check_status');
            $table->text('booking_check_last_error')->nullable()->after('booking_checked_at');
            $table->json('booking_check_result')->nullable()->after('booking_check_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'exclude_future_bookings',
                'exclude_past_bookings',
                'booking_check_pending',
                'booking_check_clear',
                'booking_future_hit',
                'booking_past_hit',
                'booking_check_error',
            ]);
        });

        Schema::table('qualify_batches', function (Blueprint $table) {
            $table->dropColumn([
                'exclude_future_bookings',
                'exclude_past_bookings',
                'booking_check_pending',
                'booking_check_clear',
                'booking_future_hit',
                'booking_past_hit',
                'booking_check_error',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'booking_check_status',
                'booking_checked_at',
                'booking_check_last_error',
                'booking_check_result',
            ]);
        });
    }
};
