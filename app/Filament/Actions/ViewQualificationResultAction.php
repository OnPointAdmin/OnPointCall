<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewQualificationResultAction
{
    public static function make(): Action
    {
        return Action::make('viewQualificationResult')
            ->modalHeading(fn (Lead $record): string => 'Qualification result — '.($record->fullName() ?: $record->phone))
            ->modalDescription(fn (Lead $record): ?string => $record->qualification_status?->label())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('4xl')
            ->modalContent(fn (Lead $record) => view('filament.qualification-result', [
                'lead' => $record,
            ]));
    }
}
