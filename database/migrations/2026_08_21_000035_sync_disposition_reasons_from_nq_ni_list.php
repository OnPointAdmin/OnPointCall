<?php

use Database\Seeders\DispositionReasonSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new DispositionReasonSeeder)->run();
    }

    public function down(): void
    {
        // Canonical labels live in DispositionReasonSeeder. Removed reasons are deactivated, not deleted.
    }
};
