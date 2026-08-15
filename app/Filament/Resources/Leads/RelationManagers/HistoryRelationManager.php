<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\LeadHistory;
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
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (LeadHistory $record): string => $record->event_type->label()),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextColumn::make('detailLabel')
                    ->label('Details')
                    ->getStateUsing(fn (LeadHistory $record): string => $record->detailLabel())
                    ->wrap(),
                TextColumn::make('noteLabel')
                    ->label('Note')
                    ->getStateUsing(fn (LeadHistory $record): ?string => $record->noteLabel())
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
