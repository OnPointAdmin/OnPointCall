<?php

namespace App\Filament\Resources\CallingLists\RelationManagers;

use App\Enums\ListAssignmentAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListAssignmentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'listAssignmentHistory';

    protected static ?string $title = 'Assignment history';

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
                TextColumn::make('user_name')
                    ->label('Agent')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (ListAssignmentAction $state): string => match ($state) {
                        ListAssignmentAction::Assigned => 'success',
                        ListAssignmentAction::Unassigned => 'gray',
                    })
                    ->formatStateUsing(fn (ListAssignmentAction $state): string => $state->label()),
                TextColumn::make('actor.name')
                    ->label('By')
                    ->placeholder('System'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
