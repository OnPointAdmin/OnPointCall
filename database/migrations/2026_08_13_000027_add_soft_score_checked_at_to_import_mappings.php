<?php

use App\Models\ImportMapping;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ImportMapping::query()->orderBy('id')->each(function (ImportMapping $mapping): void {
            $columnMap = $mapping->column_map ?? [];

            if (! is_array($columnMap)) {
                return;
            }

            $submitHeader = $columnMap['original_lead_submit_date'] ?? null;

            if ($submitHeader === null || $submitHeader === '') {
                return;
            }

            if (array_key_exists('soft_score_checked_at', $columnMap)
                && $columnMap['soft_score_checked_at'] !== null
                && $columnMap['soft_score_checked_at'] !== '') {
                return;
            }

            $columnMap['soft_score_checked_at'] = $submitHeader;
            $mapping->update(['column_map' => $columnMap]);
        });
    }

    public function down(): void
    {
        ImportMapping::query()->orderBy('id')->each(function (ImportMapping $mapping): void {
            $columnMap = $mapping->column_map ?? [];

            if (! is_array($columnMap) || ! array_key_exists('soft_score_checked_at', $columnMap)) {
                return;
            }

            $submitHeader = $columnMap['original_lead_submit_date'] ?? null;

            if ($submitHeader !== null
                && $submitHeader !== ''
                && ($columnMap['soft_score_checked_at'] ?? null) === $submitHeader) {
                unset($columnMap['soft_score_checked_at']);
                $mapping->update(['column_map' => $columnMap]);
            }
        });
    }
};
