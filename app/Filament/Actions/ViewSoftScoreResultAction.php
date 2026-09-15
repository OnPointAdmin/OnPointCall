<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewSoftScoreResultAction
{
    public static function make(): Action
    {
        return Action::make('viewSoftScoreResult')
            ->modalHeading(fn (Lead $record): string => 'Soft Score result — '.($record->fullName() ?: $record->phone))
            ->modalDescription(fn (Lead $record): ?string => $record->soft_score_status?->label())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl')
            ->modalContent(fn (Lead $record) => view('filament.soft-score-result', [
                'lead' => $record,
            ]));
    }
}
