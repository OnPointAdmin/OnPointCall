<?php

use App\Support\SoftScoreCounters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $batches = DB::table('qualify_batches')
            ->where('run_soft_score', true)
            ->get(['id']);

        foreach ($batches as $batch) {
            $codes = DB::table('qualify_batch_leads as qbl')
                ->join('leads', 'leads.id', '=', 'qbl.lead_id')
                ->where('qbl.qualify_batch_id', $batch->id)
                ->whereIn('leads.soft_score_status', ['complete', 'recent'])
                ->pluck('leads.soft_score_code');

            $qualified = 0;
            $notQualified = 0;

            foreach ($codes as $code) {
                if (SoftScoreCounters::isNotQualifiedCode(is_string($code) ? $code : null)) {
                    $notQualified++;
                } else {
                    $qualified++;
                }
            }

            DB::table('qualify_batches')->where('id', $batch->id)->update([
                'soft_score_qualified' => $qualified,
                'soft_score_not_qualified' => $notQualified,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('qualify_batches')
            ->where('run_soft_score', true)
            ->where('soft_score_not_qualified', '>', 0)
            ->update([
                'soft_score_qualified' => DB::raw('soft_score_qualified + soft_score_not_qualified'),
                'soft_score_not_qualified' => 0,
            ]);
    }
};
