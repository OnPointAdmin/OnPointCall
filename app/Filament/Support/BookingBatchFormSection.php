<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class BookingBatchFormSection
{
    /**
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            BatchCheckFormSections::fullWidth(Section::make('Booking check')
                ->schema([
                    TextInput::make('booking_check_pending')
                        ->label('Pending')
                        ->numeric(),
                    TextInput::make('booking_check_clear')
                        ->label('Clear')
                        ->numeric(),
                    TextInput::make('booking_future_hit')
                        ->label('Future bookings')
                        ->numeric(),
                    TextInput::make('booking_past_hit')
                        ->label('Past bookings')
                        ->numeric(),
                    TextInput::make('booking_check_error')
                        ->label('Errors')
                        ->numeric(),
                ])
                ->columns(5)
                ->visible(fn (Get $get): bool => (bool) $get('exclude_future_bookings') || (bool) $get('exclude_past_bookings'))),
        ];
    }
}
