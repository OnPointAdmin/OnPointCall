<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->whereIn('soft_score_status', ['qualified', 'not_qualified'])
            ->update(['soft_score_status' => 'complete']);

        DB::table('import_batches')
            ->where('soft_score_not_qualified', '>', 0)
            ->update([
                'soft_score_qualified' => DB::raw('soft_score_qualified + soft_score_not_qualified'),
                'soft_score_not_qualified' => 0,
            ]);
    }

    public function down(): void
    {
        // Irreversible: previous qualified vs not_qualified distinction is not recoverable.
    }
};
