<?php

namespace App\Filament\Resources\CallingLists\Tables;

use App\Models\CallingList;
use App\Services\Leads\DialableInventoryService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CallingListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('lead_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('cadence.name')
                    ->label('Cadence')
                    ->sortable(),
                TextColumn::make('leads_count')
                    ->label('Total leads')
                    ->counts('leads')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ready_now')
                    ->label('Ready now')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => app(DialableInventoryService::class)
                        ->forList($record)
                        ->readyNow)
                    ->tooltip('Callable leads that can be dialed right now (legal hours and cadence).'),
                TextColumn::make('waiting')
                    ->label('Waiting')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => app(DialableInventoryService::class)
                        ->forList($record)
                        ->waiting)
                    ->tooltip('Callable leads waiting on cadence, legal hours, or an active claim.'),
                TextColumn::make('max_attempts_override')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
