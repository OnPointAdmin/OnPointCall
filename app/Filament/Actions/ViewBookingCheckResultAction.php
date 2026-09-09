<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Actions\Action;

class ViewBookingCheckResultAction
{
    public static function make(): Action
    {
        return Action::make('viewBookingCheckResult')
            ->modalHeading(fn (Lead $record): string => 'Booking check — '.($record->fullName() ?: $record->phone))
            ->modalDescription(fn (Lead $record): ?string => $record->bookingDetailLabel() ?? $record->booking_check_status?->label())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('3xl')
            ->modalContent(fn (Lead $record) => view('filament.booking-check-result', [
                'lead' => $record,
            ]));
    }
}
