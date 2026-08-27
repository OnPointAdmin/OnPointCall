<?php

namespace App\Filament\Resources\CallingLists\Tables;

use App\DataTransferObjects\DialableInventory;
use App\Models\CallingList;
use App\Services\Leads\DialableInventoryService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CallingListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->getStateUsing(fn (CallingList $record): int => self::inventory($record)->readyNow)
                    ->color(fn (CallingList $record): string => self::inventory($record)->hasQueuePressure()
                        ? 'danger'
                        : 'gray')
                    ->tooltip('Callable leads that can be dialed right now (legal hours and cadence).'),
                TextColumn::make('waiting_cadence')
                    ->label('Cadence wait')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => self::inventory($record)->waitingCadence)
                    ->description(fn (CallingList $record): ?string => self::cadenceWaitDescription($record))
                    ->tooltip('Callable leads in legal hours waiting on the next cadence day-part window.'),
                TextColumn::make('waiting_hours')
                    ->label('Hours wait')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => self::inventory($record)->waitingHours)
                    ->tooltip('Callable leads outside legal calling hours right now.'),
                TextColumn::make('max_attempts')
                    ->label('Max attempts')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => self::inventory($record)->maxAttempts)
                    ->tooltip('Callable leads at max attempts. Recycle to serve again.'),
                TextColumn::make('callbacks_due')
                    ->label('Due callbacks')
                    ->numeric()
                    ->getStateUsing(fn (CallingList $record): int => self::inventory($record)->callbacksDue)
                    ->tooltip('Callbacks on this list whose scheduled time has passed.'),
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function inventory(CallingList $record): DialableInventory
    {
        return app(DialableInventoryService::class)->forList($record);
    }

    private static function cadenceWaitDescription(CallingList $record): ?string
    {
        $inventory = self::inventory($record);

        if ($inventory->waitingCadence === 0) {
            return null;
        }

        $description = $inventory->cadenceDayPartDescription();

        return $description !== '' ? $description : null;
    }
}
