<?php

namespace App\Filament\Support;

use App\Enums\SoftScoreStatus;
use App\Models\ImportBatch;
use App\Models\QualifyBatch;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;

class BatchLeadsFilters
{
    public static function softScoreCode(RelationManager $manager): SelectFilter
    {
        return SelectFilter::make('soft_score_code')
            ->label('Soft score')
            ->options(fn (): array => self::distinctSoftScoreCodes($manager->getOwnerRecord()))
            ->searchable();
    }

    public static function softScoreStatus(): SelectFilter
    {
        return SelectFilter::make('soft_score_status')
            ->label('Soft score status')
            ->options(collect(SoftScoreStatus::cases())->mapWithKeys(
                fn (SoftScoreStatus $status): array => [$status->value => $status->label()]
            ));
    }

    /**
     * @return array<string, string>
     */
    private static function distinctSoftScoreCodes(Model $owner): array
    {
        if (! $owner instanceof ImportBatch && ! $owner instanceof QualifyBatch) {
            return [];
        }

        return $owner->leads()
            ->whereNotNull('leads.soft_score_code')
            ->where('leads.soft_score_code', '!=', '')
            ->select('leads.soft_score_code')
            ->distinct()
            ->orderBy('leads.soft_score_code')
            ->pluck('leads.soft_score_code', 'leads.soft_score_code')
            ->all();
    }
}
