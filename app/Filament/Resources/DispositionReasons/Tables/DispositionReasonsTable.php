<?php

namespace App\Filament\Resources\DispositionReasons\Tables;

use App\Models\DispositionDefinition;
use App\Support\CompanyContext;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DispositionReasonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('disposition')
                    ->badge()
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_string($state) || $state === '') {
                            return '';
                        }

                        $companyId = CompanyContext::idOrAuthenticated();

                        return $companyId
                            ? (DispositionDefinition::labelForSlug($companyId, $state) ?? $state)
                            : $state;
                    }),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('disposition')
                    ->options(fn (): array => DispositionDefinition::withoutGlobalScopes()
                        ->where('company_id', CompanyContext::idOrAuthenticated())
                        ->where('requires_reason', true)
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->pluck('label', 'slug')
                        ->all()),
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
