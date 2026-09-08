<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\DncStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Models\Lead;
use App\Support\CompanyTimezone;
use App\Support\PhoneNormalizer;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Status and queue')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (?LeadStatus $state): ?string => $state?->label()),
                        TextEntry::make('lead_type')
                            ->label('Lead type')
                            ->formatStateUsing(fn (Lead $record): string => $record->leadTypeName()),
                        TextEntry::make('callingList.name')
                            ->label('Calling list')
                            ->placeholder('Holding'),
                        TextEntry::make('calling_list_assigned_at')
                            ->label('Added to list')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->calling_list_assigned_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('queue_rank')
                            ->label('Queue rank'),
                        TextEntry::make('attempt_count')
                            ->label('Attempts'),
                        TextEntry::make('next_day_part')
                            ->label('Next day part')
                            ->placeholder('—'),
                        TextEntry::make('last_attempt_at')
                            ->label('Last attempt')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->last_attempt_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('callback_at')
                            ->label('Callback at')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->callback_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('callbackOwner.name')
                            ->label('Callback owner')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('phone')
                            ->formatStateUsing(fn (?string $state): ?string => $state !== null
                                ? (PhoneNormalizer::formatForDisplay($state) ?? $state)
                                : null),
                        TextEntry::make('phone_2')
                            ->label('Phone 2')
                            ->formatStateUsing(fn (?string $state): ?string => $state !== null
                                ? (PhoneNormalizer::formatForDisplay($state) ?? $state)
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('first_name')
                            ->placeholder('—'),
                        TextEntry::make('last_name')
                            ->placeholder('—'),
                        TextEntry::make('first_name_2')
                            ->label('First name 2')
                            ->placeholder('—'),
                        TextEntry::make('last_name_2')
                            ->label('Last name 2')
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->label('Email address')
                            ->placeholder('—'),
                        TextEntry::make('timezone')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Address')
                    ->schema([
                        TextEntry::make('address')
                            ->placeholder('—'),
                        TextEntry::make('address_2')
                            ->label('Address 2')
                            ->placeholder('—'),
                        TextEntry::make('city')
                            ->placeholder('—'),
                        TextEntry::make('state')
                            ->placeholder('—'),
                        TextEntry::make('zip')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Demographics')
                    ->schema([
                        TextEntry::make('age_range')
                            ->label('Age range')
                            ->placeholder('—'),
                        TextEntry::make('annual_income')
                            ->label('Annual income')
                            ->placeholder('—'),
                        TextEntry::make('marital_status')
                            ->label('Marital status')
                            ->placeholder('—'),
                        TextEntry::make('gender')
                            ->placeholder('—'),
                        TextEntry::make('home_owner')
                            ->label('Homeowner')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('venue')
                            ->placeholder('—'),
                        TextEntry::make('event')
                            ->placeholder('—'),
                        TextEntry::make('partner_list')
                            ->label('Partner list')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('original_lead_submit_date')
                            ->label('Original submit date')
                            ->placeholder('—'),
                        TextEntry::make('external_lead_id')
                            ->label('External ID')
                            ->placeholder('—'),
                        TextEntry::make('file_name')
                            ->label('File name')
                            ->placeholder('—'),
                        TextEntry::make('importBatch.id')
                            ->label('Import batch')
                            ->placeholder('—'),
                        TextEntry::make('imported_at')
                            ->label('Imported at')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->imported_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Tour')
                    ->schema([
                        TextEntry::make('tour_location')
                            ->label('Tour location')
                            ->placeholder('—'),
                        TextEntry::make('tour_date_start')
                            ->label('Tour date start')
                            ->placeholder('—'),
                        TextEntry::make('tour_date')
                            ->label('Tour date')
                            ->placeholder('—'),
                        TextEntry::make('premiums')
                            ->placeholder('—'),
                        TextEntry::make('tour_result')
                            ->label('Tour result')
                            ->placeholder('—'),
                        TextEntry::make('tour_or_no_show')
                            ->label('Tour / no show')
                            ->placeholder('—'),
                        TextEntry::make('booking_id')
                            ->label('Booking ID')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->visible(fn (Lead $record): bool => self::hasTourData($record)),
                Section::make('Qualification')
                    ->schema([
                        TextEntry::make('qualification_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?QualificationStatus $state): ?string => $state?->label())
                            ->placeholder('—'),
                        TextEntry::make('qualification_checked_at')
                            ->label('Last checked')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->qualification_checked_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('qualified_partners')
                            ->label('Currently qualified partners')
                            ->state(fn (Lead $record): ?string => self::qualifiedPartnersLabel($record))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('qualification_booking_companies')
                            ->label('Qualified booking companies')
                            ->state(fn (Lead $record): ?string => self::bookingCompaniesLabel($record))
                            ->listWithLineBreaks()
                            ->placeholder('—')
                            ->visible(fn (Lead $record): bool => $record->qualificationCompanies('qualifiedCompaniesBooking') !== [])
                            ->columnSpanFull(),
                        TextEntry::make('qualification_last_error')
                            ->label('Last error')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ])
                    ->columns(3),
                Section::make('Soft Score')
                    ->schema([
                        TextEntry::make('soft_score_code')
                            ->label('Code')
                            ->placeholder('—'),
                        TextEntry::make('soft_score_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?SoftScoreStatus $state): ?string => $state?->label())
                            ->placeholder('—'),
                        TextEntry::make('soft_score_checked_at')
                            ->label('Last checked')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->soft_score_checked_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('soft_score_last_error')
                            ->label('Last error')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ])
                    ->columns(3),
                Section::make('DNC')
                    ->schema([
                        TextEntry::make('dnc_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?DncStatus $state): ?string => $state?->label())
                            ->placeholder('—'),
                        TextEntry::make('dnc_details')
                            ->label('Details')
                            ->state(fn (Lead $record): ?string => $record->dncDetailLabel())
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                        TextEntry::make('dnc_checked_at')
                            ->label('Last checked')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->dnc_checked_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('dnc_last_error')
                            ->label('Last error')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ])
                    ->columns(3),
                Section::make('RND')
                    ->schema([
                        TextEntry::make('rnd_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?RndStatus $state): ?string => $state?->label())
                            ->placeholder('—'),
                        TextEntry::make('rnd_checked_at')
                            ->label('Last checked')
                            ->formatStateUsing(fn (Lead $record): ?string => CompanyTimezone::display(
                                $record->rnd_checked_at,
                                $record->company_id,
                            ))
                            ->placeholder('—'),
                        TextEntry::make('rnd_last_error')
                            ->label('Last error')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ])
                    ->columns(3),
                Section::make('Extra fields')
                    ->schema([
                        KeyValueEntry::make('extra_fields')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),
                    ])
                    ->visible(fn (Lead $record): bool => is_array($record->extra_fields) && $record->extra_fields !== [])
                    ->columnSpanFull(),
            ]);
    }

    private static function hasTourData(Lead $record): bool
    {
        if ($record->lead_type === 'tnb') {
            return true;
        }

        return filled($record->tour_location)
            || filled($record->tour_date_start)
            || filled($record->tour_date)
            || filled($record->premiums)
            || filled($record->tour_result)
            || filled($record->tour_or_no_show)
            || filled($record->booking_id);
    }

    private static function qualifiedPartnersLabel(Lead $record): ?string
    {
        $names = $record->qualifiedPartnerNames();

        return $names !== [] ? implode(', ', $names) : null;
    }

    private static function bookingCompaniesLabel(Lead $record): ?string
    {
        $lines = [];

        foreach ($record->qualificationCompanies('qualifiedCompaniesBooking') as $company) {
            $parts = [$company['name']];

            if ($company['vertical'] !== null) {
                $parts[] = $company['vertical'];
            }

            if ($company['priority'] !== null) {
                $parts[] = 'Priority '.$company['priority'];
            }

            if ($company['combination'] !== null) {
                $parts[] = $company['combination'];
            }

            $lines[] = implode(' · ', $parts);
        }

        return $lines !== [] ? implode("\n", $lines) : null;
    }
}
