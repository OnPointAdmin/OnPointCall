<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewLeadCheckErrorsAction
{
    public static function make(): Action
    {
        return Action::make('viewLeadCheckErrors')
            ->modalHeading(fn (Lead $record): string => 'Check errors — '.($record->fullName() ?: $record->phone))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl')
            ->modalContent(fn (Lead $record) => view('filament.lead-check-errors', [
                'lead' => $record,
            ]));
    }
}
