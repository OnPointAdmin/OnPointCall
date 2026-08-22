<?php

namespace App\Filament\Resources\Cadences\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CadencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('dayParts')
                    ->label('Day parts')
                    ->formatStateUsing(function ($record): string {
                        $parts = $record->dayParts
                            ->where('enabled', true)
                            ->sortBy('rotation_order')
                            ->pluck('day_part')
                            ->map(fn (string $part): string => ucfirst($part))
                            ->implode(' → ');

                        return $parts !== '' ? $parts : '—';
                    }),
                TextColumn::make('attemptGaps')
                    ->label('Wait rules')
                    ->formatStateUsing(function ($record): string {
                        return $record->attemptGaps
                            ->sortBy('after_attempt')
                            ->map(fn ($gap): string => sprintf(
                                '%d→%d %s',
                                $gap->after_attempt,
                                $gap->wait_value,
                                $gap->wait_unit->value,
                            ))
                            ->implode(', ');
                    }),
                IconColumn::make('prioritize_unattempted')
                    ->label('Fresh first')
                    ->boolean(),
                TextColumn::make('calling_lists_count')
                    ->label('Lists')
                    ->counts('callingLists')
                    ->numeric(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
