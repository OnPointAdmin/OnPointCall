<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\LeadHistory;
use App\Support\CompanyTimezone;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'History';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->formatStateUsing(fn (LeadHistory $record): ?string => CompanyTimezone::display(
                        $record->occurred_at,
                        $record->company_id,
                        'M j, Y g:i A T',
                    ))
                    ->grow(false)
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (LeadHistory $record): string => $record->event_type->label())
                    ->grow(false),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->grow(false),
                TextColumn::make('detailLabel')
                    ->label('Details')
                    ->getStateUsing(fn (LeadHistory $record): string => $record->detailLabel())
                    ->grow()
                    ->wrap()
                    ->alignStart()
                    ->tooltip(fn (LeadHistory $record): ?string => strlen($record->detailLabel()) > 80
                        ? $record->detailLabel()
                        : null),
                TextColumn::make('noteLabel')
                    ->label('Note')
                    ->getStateUsing(fn (LeadHistory $record): ?string => $record->noteLabel())
                    ->placeholder('—')
                    ->grow(false)
                    ->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
