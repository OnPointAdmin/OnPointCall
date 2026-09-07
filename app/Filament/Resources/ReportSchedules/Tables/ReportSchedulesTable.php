<?php

namespace App\Filament\Resources\ReportSchedules\Tables;

use App\Filament\Resources\ReportSchedules\Actions\SendReportScheduleAction;
use App\Support\Weekdays;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReportSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('report_type')
                    ->label('Report')
                    ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Date range')
                    ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? 'As of now')
                    ->sortable(),
                TextColumn::make('days_of_week')
                    ->label('Days')
                    ->formatStateUsing(fn ($state): string => Weekdays::labels(is_array($state) ? $state : [])),
                TextColumn::make('send_times')
                    ->label('Times')
                    ->formatStateUsing(function ($state): string {
                        $times = is_array($state) ? $state : [];

                        return collect($times)
                            ->map(fn (mixed $time): string => is_string($time) ? $time : '')
                            ->filter()
                            ->implode(', ') ?: '—';
                    }),
                TextColumn::make('recipients_count')
                    ->label('Recipients')
                    ->counts('recipients')
                    ->numeric(),
                ToggleColumn::make('enabled'),
            ])
            ->recordActions([
                SendReportScheduleAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
