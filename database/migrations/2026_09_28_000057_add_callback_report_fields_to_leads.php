<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('booking_number')->nullable()->after('booking_id');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('premiums');
            $table->string('deposit_type')->nullable()->after('deposit_amount');
        });

        DB::table('leads')
            ->whereNull('booking_number')
            ->whereNotNull('booking_id')
            ->update(['booking_number' => DB::raw('booking_id')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['booking_number', 'deposit_amount', 'deposit_type']);
        });
    }
};
