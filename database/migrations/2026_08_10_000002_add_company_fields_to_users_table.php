<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('agent')->after('email');
            $table->boolean('active')->default(true)->after('role');
            $table->string('google_id')->nullable()->after('active');
            $table->string('microsoft_id')->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->unique('email');
            $table->dropIndex(['company_id', 'role']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['role', 'active', 'google_id', 'microsoft_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
