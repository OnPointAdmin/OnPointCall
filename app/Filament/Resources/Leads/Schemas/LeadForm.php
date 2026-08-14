<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Filament\Support\LeadTypeSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('phone_2')
                    ->label('Phone 2')
                    ->tel(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('address'),
                TextInput::make('address_2')
                    ->label('Address 2'),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('zip'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('age_range'),
                TextInput::make('annual_income'),
                TextInput::make('marital_status'),
                TextInput::make('gender'),
                TextInput::make('home_owner'),
                TextInput::make('original_lead_submit_date'),
                TextInput::make('venue'),
                TextInput::make('event'),
                TextInput::make('tour_location'),
                TextInput::make('tour_date_start'),
                TextInput::make('tour_date'),
                TextInput::make('premiums'),
                TextInput::make('tour_result'),
                TextInput::make('tour_or_no_show')
                    ->label('Tour / no show'),
                TextInput::make('external_lead_id'),
                TextInput::make('booking_id'),
                TextInput::make('timezone'),
                Select::make('status')
                    ->options(LeadStatus::class)
                    ->default('holding')
                    ->required(),
                TextInput::make('attempt_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('next_day_part'),
                DateTimePicker::make('last_attempt_at'),
                DateTimePicker::make('callback_at'),
                Select::make('callback_owner_id')
                    ->relationship('callbackOwner', 'name'),
                Select::make('calling_list_id')
                    ->relationship('callingList', 'name'),
                DateTimePicker::make('imported_at'),
                Select::make('import_batch_id')
                    ->relationship('importBatch', 'id'),
                Textarea::make('partner_list')
                    ->columnSpanFull(),
                TextInput::make('file_name')
                    ->label('File name'),
                TextInput::make('queue_rank')
                    ->required()
                    ->numeric()
                    ->default(0),
                LeadTypeSelect::make(allowCreate: true),
                TextInput::make('extra_fields'),
                TextInput::make('soft_score_code')
                    ->label('Soft Score code'),
                Select::make('soft_score_status')
                    ->label('Soft Score status')
                    ->options(SoftScoreStatus::class),
                DateTimePicker::make('soft_score_checked_at')
                    ->label('Soft Score last checked'),
                Textarea::make('soft_score_last_error')
                    ->label('Soft Score last error')
                    ->columnSpanFull(),
                Select::make('qualification_status')
                    ->options(QualificationStatus::class),
                DateTimePicker::make('qualification_checked_at'),
                Textarea::make('qualification_last_error')
                    ->columnSpanFull(),
            ]);
    }
}
