<?php

namespace App\Filament\Resources\Dispositions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DispositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('outcome')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label')
                        ? $state->label()
                        : (string) $state),
                TextColumn::make('color')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label')
                        ? $state->label()
                        : (string) $state),
                TextColumn::make('button_group')
                    ->label('Group')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('Standard')
                    ->boolean(),
                IconColumn::make('active')
                    ->boolean(),
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
