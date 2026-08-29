<?php

use Database\Seeders\DispositionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new DispositionSeeder)->run();
    }

    public function down(): void
    {
        // Definitions are company-owned reference data; leave rows on rollback.
    }
};
