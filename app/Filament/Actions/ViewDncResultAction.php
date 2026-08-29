<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewDncResultAction
{
    public static function make(): Action
    {
        return Action::make('viewDncResult')
            ->modalHeading(fn (Lead $record): string => 'DNC result — '.($record->fullName() ?: $record->phone))
            ->modalDescription(fn (Lead $record): ?string => $record->dncDetailLabel() ?? $record->dnc_status?->label())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('3xl')
            ->modalContent(fn (Lead $record) => view('filament.dnc-result', [
                'lead' => $record,
            ]));
    }
}
