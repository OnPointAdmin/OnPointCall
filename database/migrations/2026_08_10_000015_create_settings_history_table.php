<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('setting_key');
            $table->jsonb('before_value')->nullable();
            $table->jsonb('after_value')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['company_id', 'changed_at']);
            $table->index(['company_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_history');
    }
};
