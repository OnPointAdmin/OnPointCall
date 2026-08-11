<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('booking_url_template')->nullable();
            $table->jsonb('booking_param_map')->nullable();
            $table->unsignedSmallInteger('max_attempts')->default(6);
            $table->unsignedSmallInteger('claim_ttl_minutes')->default(20);
            $table->boolean('dashboard_email_enabled')->default(false);
            $table->time('dashboard_email_send_time')->default('07:00:00');
            $table->string('dashboard_email_timezone')->default('America/New_York');
            $table->string('soft_score_originator')->nullable();
            $table->string('soft_score_base_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
