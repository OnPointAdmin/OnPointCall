<?php

namespace App\Filament\Resources\LeadHistories\Schemas;

use App\Enums\Disposition;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\LeadHistory;
use App\Support\CompanyTimezone;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity')
                    ->schema([
                        TextEntry::make('occurred_at')
                            ->label('When')
                            ->formatStateUsing(fn (LeadHistory $record): ?string => CompanyTimezone::display(
                                $record->occurred_at,
                                $record->company_id,
                                'M j, Y g:i A T',
                            )),
                        TextEntry::make('event_type')
                            ->label('Event')
                            ->badge()
                            ->formatStateUsing(fn (LeadHistory $record): string => $record->event_type->label()),
                        TextEntry::make('actor.name')
                            ->label('Actor')
                            ->placeholder('System'),
                        TextEntry::make('payload.disposition')
                            ->label('Disposition')
                            ->badge()
                            ->formatStateUsing(function (LeadHistory $record): ?string {
                                $value = $record->payload['disposition'] ?? null;

                                if (! is_string($value) || $value === '') {
                                    return null;
                                }

                                return Disposition::tryFrom($value)?->label() ?? $value;
                            })
                            ->placeholder('—'),
                        TextEntry::make('details')
                            ->label('Details')
                            ->state(fn (LeadHistory $record): string => $record->detailLabel()),
                        TextEntry::make('note')
                            ->label('Note')
                            ->state(fn (LeadHistory $record): ?string => $record->noteLabel())
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Lead')
                    ->schema([
                        TextEntry::make('lead.phone')
                            ->label('Phone')
                            ->url(fn (LeadHistory $record): ?string => $record->lead_id
                                ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('lead_name')
                            ->label('Name')
                            ->state(fn (LeadHistory $record): string => trim(
                                ($record->lead?->first_name ?? '').' '.($record->lead?->last_name ?? ''),
                            ) ?: '—'),
                        TextEntry::make('lead.callingList.name')
                            ->label('List')
                            ->placeholder('Holding'),
                    ])
                    ->columns(2),
            ]);
    }
}
