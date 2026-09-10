<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\BookingCheckStatus;
use App\Enums\DncStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\LeadTablePreset;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Filament\Actions\ViewBookingCheckResultAction;
use App\Filament\Actions\ViewDncResultAction;
use App\Filament\Actions\ViewQualificationResultAction;
use App\Models\DispositionDefinition;
use App\Models\Lead;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class LeadsTableColumns
{
    /**
     * @return list<TextColumn>
     */
    public static function make(LeadTablePreset $preset): array
    {
        $starters = $preset->starterColumnNames();

        $hidden = static fn (string $name): bool => ! in_array($name, $starters, true);

        $columns = [
            TextColumn::make('id')
                ->label('ID')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('leads.id', $direction))
                ->toggleable(isToggledHiddenByDefault: $hidden('id')),
            TextColumn::make('phone')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('phone')),
            TextColumn::make('external_lead_id')
                ->label('External ID')
                ->searchable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('external_lead_id')),
            TextColumn::make('first_name')
                ->label($preset === LeadTablePreset::Callbacks ? 'Name' : 'First name')
                ->searchable()
                ->sortable()
                ->formatStateUsing(fn (Lead $record): string => $preset === LeadTablePreset::Callbacks
                    ? ($record->fullName() ?: '—')
                    : ($record->first_name ?? '—'))
                ->toggleable(isToggledHiddenByDefault: $hidden('first_name')),
            TextColumn::make('last_name')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('last_name')),
            TextColumn::make('state')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('state')),
            TextColumn::make('zip')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: $hidden('zip')),
            TextColumn::make('venue')
                ->searchable()
                ->sortable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('venue')),
            TextColumn::make('event')
                ->searchable()
                ->sortable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('event')),
            TextColumn::make('status')
                ->badge()
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('status')),
            TextColumn::make('last_disposition')
                ->label('Last Disp')
                ->badge()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('last_disposition'))
                ->getStateUsing(function (Lead $record): ?string {
                    $value = $record->latestDisposition?->payload['disposition'] ?? null;

                    if (! is_string($value) || $value === '') {
                        return null;
                    }

                    return DispositionDefinition::labelForSlug($record->company_id, $value) ?? $value;
                }),
            TextColumn::make('last_attempt_at')
                ->label('Last Call Date')
                ->dateTime()
                ->sortable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('last_attempt_at')),
            TextColumn::make('attempt_count')
                ->label('Attempts')
                ->numeric()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('attempt_count')),
            TextColumn::make('calling_list_assigned_at')
                ->label('Added to list')
                ->dateTime()
                ->sortable()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('calling_list_assigned_at')),
        ];

        if (! $preset->hidesCallingListColumn()) {
            $columns[] = TextColumn::make('callingList.name')
                ->label('List')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: $hidden('callingList.name'));
        }

        $columns = [
            ...$columns,
            TextColumn::make('file_name')
                ->label('Source file')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: $hidden('file_name')),
            TextColumn::make('lead_type')
                ->badge()
                ->toggleable(isToggledHiddenByDefault: $hidden('lead_type')),
            TextColumn::make('partner_list')
                ->label('Partner List')
                ->wrap()
                ->limit(40)
                ->tooltip(fn (Lead $record): ?string => filled($record->partner_list) ? $record->partner_list : null)
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hidden('partner_list')),
            TextColumn::make('qualified_partners')
                ->label('Qualified · Partners')
                ->wrap()
                ->placeholder('—')
                ->getStateUsing(function (Lead $record): ?string {
                    $names = $record->qualifiedPartnerNames();

                    return $names === [] ? null : implode(', ', $names);
                })
                ->toggleable(isToggledHiddenByDefault: $hidden('qualified_partners')),
            TextColumn::make('soft_score_code')
                ->label('Soft score')
                ->badge()
                ->placeholder(fn (Lead $record): string => match ($record->soft_score_status) {
                    SoftScoreStatus::Pending => 'Pending',
                    SoftScoreStatus::Error => 'Error',
                    SoftScoreStatus::Complete => '—',
                    SoftScoreStatus::Recent => 'Recently checked',
                    default => '—',
                })
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('soft_score_code')),
            TextColumn::make('soft_score_status')
                ->label('Soft score status')
                ->badge()
                ->formatStateUsing(fn (?SoftScoreStatus $state): ?string => $state?->label())
                ->toggleable(isToggledHiddenByDefault: $hidden('soft_score_status')),
            TextColumn::make('soft_score_checked_at')
                ->label('Soft score last checked')
                ->dateTime()
                ->toggleable(isToggledHiddenByDefault: $hidden('soft_score_checked_at')),
            TextColumn::make('rnd_status')
                ->label('RND')
                ->badge()
                ->formatStateUsing(fn (?RndStatus $state): ?string => $state?->label())
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('rnd_status')),
            TextColumn::make('qualification_status')
                ->label('Qualification')
                ->badge()
                ->color(fn (?QualificationStatus $state): string => match ($state) {
                    QualificationStatus::Qualified => 'success',
                    QualificationStatus::NotQualified => 'warning',
                    QualificationStatus::Error => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?QualificationStatus $state): ?string => $state?->label())
                ->tooltip(fn (Lead $record): ?string => $record->qualification_status
                    ? 'View qualification response'
                    : null)
                ->action(ViewQualificationResultAction::make())
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('qualification_status')),
            TextColumn::make('dnc_status')
                ->label('DNC')
                ->badge()
                ->color(fn (?DncStatus $state): string => match ($state) {
                    DncStatus::Clear => 'success',
                    DncStatus::Hit, DncStatus::Invalid => 'danger',
                    DncStatus::Error => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?DncStatus $state): ?string => $state?->label())
                ->tooltip(fn (Lead $record): ?string => $record->dnc_status
                    ? ($record->dncDetailLabel() ?? 'View DNC scrub result')
                    : null)
                ->action(ViewDncResultAction::make())
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('dnc_status')),
            TextColumn::make('booking_check_status')
                ->label('Booking')
                ->badge()
                ->color(fn (?BookingCheckStatus $state): string => match ($state) {
                    BookingCheckStatus::Clear => 'success',
                    BookingCheckStatus::FutureHit, BookingCheckStatus::PastHit, BookingCheckStatus::Error => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?BookingCheckStatus $state): ?string => $state?->label())
                ->tooltip(fn (Lead $record): ?string => $record->booking_check_status
                    ? ($record->bookingDetailLabel() ?? 'View booking check result')
                    : null)
                ->action(ViewBookingCheckResultAction::make())
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('booking_check_status')),
            TextColumn::make('error')
                ->label('Error')
                ->wrap()
                ->limit(100)
                ->tooltip(fn (?string $state): ?string => $state)
                ->getStateUsing(function (Lead $record): ?string {
                    $parts = array_filter([
                        $record->soft_score_last_error,
                        $record->rnd_last_error,
                        $record->qualification_last_error,
                        $record->dnc_last_error,
                        $record->booking_check_last_error,
                    ]);

                    return $parts === [] ? null : implode(' | ', $parts);
                })
                ->toggleable(isToggledHiddenByDefault: $hidden('error')),
            TextColumn::make('callback_at')
                ->dateTime()
                ->sortable()
                ->color(fn (Lead $record): string => $record->callback_at?->isPast() ? 'danger' : 'gray')
                ->toggleable(isToggledHiddenByDefault: $hidden('callback_at')),
            TextColumn::make('callbackOwner.name')
                ->label('Owner')
                ->placeholder('Unassigned')
                ->toggleable(isToggledHiddenByDefault: $hidden('callbackOwner.name')),
            TextColumn::make('callback_owner_active')
                ->label('Owner Active')
                ->state(fn (Lead $record): string => $record->callbackOwner?->active ? 'Yes' : 'No')
                ->color(fn (Lead $record): string => $record->callbackOwner?->active ? 'success' : 'danger')
                ->toggleable(isToggledHiddenByDefault: $hidden('callback_owner_active')),
            TextColumn::make('age_range')
                ->toggleable(isToggledHiddenByDefault: $hidden('age_range')),
            TextColumn::make('annual_income')
                ->label('Income range')
                ->toggleable(isToggledHiddenByDefault: $hidden('annual_income')),
            TextColumn::make('marital_status')
                ->toggleable(isToggledHiddenByDefault: $hidden('marital_status')),
            TextColumn::make('gender')
                ->toggleable(isToggledHiddenByDefault: $hidden('gender')),
            TextColumn::make('home_owner')
                ->toggleable(isToggledHiddenByDefault: $hidden('home_owner')),
            TextColumn::make('tour_location')
                ->toggleable(isToggledHiddenByDefault: $hidden('tour_location')),
            TextColumn::make('tour_date_start')
                ->toggleable(isToggledHiddenByDefault: $hidden('tour_date_start')),
            TextColumn::make('tour_date')
                ->toggleable(isToggledHiddenByDefault: $hidden('tour_date')),
            TextColumn::make('tour_result')
                ->toggleable(isToggledHiddenByDefault: $hidden('tour_result')),
            TextColumn::make('imported_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hidden('imported_at')),
        ];

        return $columns;
    }
}
