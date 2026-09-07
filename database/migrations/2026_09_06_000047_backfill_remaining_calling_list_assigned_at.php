<?php

use App\Services\Leads\CallingListAssignedAtBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CallingListAssignedAtBackfill::class)->run();
    }

    public function down(): void
    {
        //
    }
};
