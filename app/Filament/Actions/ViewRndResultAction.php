<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewRndResultAction
{
    public static function make(): Action
    {
        return Action::make('viewRndResult')
            ->modalHeading(fn (Lead $record): string => 'RND result — '.($record->fullName() ?: $record->phone))
            ->modalDescription(fn (Lead $record): ?string => $record->rnd_status?->label())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl')
            ->modalContent(fn (Lead $record) => view('filament.rnd-result', [
                'lead' => $record,
            ]));
    }
}
