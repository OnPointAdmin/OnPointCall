<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_filename')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total_rows')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inserted_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('duplicate_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conflict_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lead_type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('run_soft_score')
                    ->boolean(),
                TextColumn::make('soft_score_pending')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('soft_score_qualified')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('soft_score_not_qualified')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('soft_score_error')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
