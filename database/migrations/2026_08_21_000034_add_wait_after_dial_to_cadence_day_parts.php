<?php

use App\Support\CadenceDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cadence_day_parts', function (Blueprint $table) {
            $table->unsignedInteger('wait_after_value')->nullable()->after('window_end');
            $table->string('wait_after_unit')->nullable()->after('wait_after_value');
        });

        $this->backfillDefaultWaits();
    }

    public function down(): void
    {
        Schema::table('cadence_day_parts', function (Blueprint $table) {
            $table->dropColumn(['wait_after_value', 'wait_after_unit']);
        });
    }

    private function backfillDefaultWaits(): void
    {
        $defaults = CadenceDefaults::defaultWaitAfterDial();

        foreach (DB::table('cadence_day_parts')->get() as $row) {
            $wait = $defaults[$row->day_part] ?? ['wait_after_value' => null, 'wait_after_unit' => null];

            DB::table('cadence_day_parts')
                ->where('id', $row->id)
                ->update($wait);
        }
    }
};
