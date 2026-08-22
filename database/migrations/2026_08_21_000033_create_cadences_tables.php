<?php

use App\Support\CadenceDefaults;
use App\Support\CadenceProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('prioritize_unattempted')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('cadence_day_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadence_id')->constrained()->cascadeOnDelete();
            $table->string('day_part');
            $table->unsignedTinyInteger('rotation_order');
            $table->boolean('enabled')->default(true);
            $table->time('window_start');
            $table->time('window_end');
            $table->timestamps();

            $table->unique(['cadence_id', 'day_part']);
        });

        Schema::create('cadence_attempt_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadence_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('after_attempt');
            $table->unsignedInteger('wait_value');
            $table->string('wait_unit');
            $table->timestamps();

            $table->unique(['cadence_id', 'after_attempt']);
        });

        Schema::table('calling_lists', function (Blueprint $table) {
            $table->foreignId('cadence_id')->nullable()->after('lead_type')->constrained()->restrictOnDelete();
        });

        $this->migrateInlineCadenceJson();

        Schema::table('calling_lists', function (Blueprint $table) {
            $table->dropColumn('cadence');
        });
    }

    public function down(): void
    {
        Schema::table('calling_lists', function (Blueprint $table) {
            $table->json('cadence')->default('{}');
        });

        Schema::table('calling_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cadence_id');
        });

        Schema::dropIfExists('cadence_attempt_gaps');
        Schema::dropIfExists('cadence_day_parts');
        Schema::dropIfExists('cadences');
    }

    private function migrateInlineCadenceJson(): void
    {
        $lists = DB::table('calling_lists')->get();
        $cadenceCache = [];

        foreach ($lists as $list) {
            $raw = $list->cadence ?? '{}';
            $decoded = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            $decoded = is_array($decoded) ? $decoded : [];

            $cacheKey = $list->company_id.'|'.json_encode($decoded);

            if (! isset($cadenceCache[$cacheKey])) {
                $cadenceCache[$cacheKey] = $this->createCadenceFromLegacyJson(
                    (int) $list->company_id,
                    $decoded,
                    count($cadenceCache) + 1,
                );
            }

            DB::table('calling_lists')
                ->where('id', $list->id)
                ->update(['cadence_id' => $cadenceCache[$cacheKey]]);
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function createCadenceFromLegacyJson(int $companyId, array $legacy, int $sequence): int
    {
        $name = $sequence === 1 ? 'Standard' : 'Migrated cadence '.$sequence;

        $existing = DB::table('cadences')
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $dayParts = $legacy['day_parts'] ?? CadenceDefaults::DAY_PARTS;
        $dayPartHours = is_array($legacy['day_part_hours'] ?? null) ? $legacy['day_part_hours'] : [];
        $minGap = (int) ($legacy['min_gap_minutes'] ?? 60);

        $dayPartRows = [];

        foreach (CadenceDefaults::DAY_PARTS as $index => $dayPart) {
            $defaults = CadenceDefaults::windows()[$dayPart];
            $custom = $dayPartHours[$dayPart] ?? null;
            $start = is_array($custom) ? (string) ($custom[0] ?? $defaults[0]) : $defaults[0];
            $end = is_array($custom) ? (string) ($custom[1] ?? $defaults[1]) : $defaults[1];

            $dayPartRows[] = [
                'day_part' => $dayPart,
                'rotation_order' => ($position = array_search($dayPart, $dayParts, true)) !== false
                    ? $position + 1
                    : $index + 1,
                'enabled' => in_array($dayPart, $dayParts, true),
                'window_start' => $start,
                'window_end' => $end,
            ];
        }

        usort($dayPartRows, fn (array $a, array $b): int => $a['rotation_order'] <=> $b['rotation_order']);

        foreach ($dayPartRows as $index => &$row) {
            $row['rotation_order'] = $index + 1;
        }
        unset($row);

        $cadence = CadenceProvisioner::create(
            companyId: $companyId,
            name: $name,
            dayParts: $dayPartRows,
            attemptGaps: [
                ['after_attempt' => 1, 'wait_value' => max(1, $minGap), 'wait_unit' => 'minutes'],
            ],
        );

        return $cadence->id;
    }
};
