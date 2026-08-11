<?php

namespace App\Filament\Pages;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CallbacksBoard extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Callbacks Board';

    protected static ?string $title = 'Callbacks Board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected string $view = 'filament.pages.callbacks-board';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
                    ->with(['callbackOwner', 'callingList'])
                    ->where('status', LeadStatus::Callback)
            )
            ->columns([
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Lead $record): string => $record->fullName() ?: '—'),
                TextColumn::make('callback_at')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (Lead $record): string => $record->callback_at?->isPast() ? 'danger' : 'gray'),
                TextColumn::make('callbackOwner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned'),
                TextColumn::make('callingList.name')
                    ->label('List'),
                TextColumn::make('callback_owner_active')
                    ->label('Owner Active')
                    ->state(fn (Lead $record): string => $record->callbackOwner?->active ? 'Yes' : 'No')
                    ->color(fn (Lead $record): string => $record->callbackOwner?->active ? 'success' : 'danger'),
            ])
            ->defaultSort('callback_at')
            ->recordActions([
                Action::make('reassign')
                    ->label('Reassign')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->form([
                        Select::make('callback_owner_id')
                            ->label('New owner')
                            ->options(fn (): array => User::query()
                                ->where('active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Lead $record, array $data): void {
                        $record->update([
                            'callback_owner_id' => $data['callback_owner_id'],
                        ]);

                        Notification::make()
                            ->title('Callback reassigned')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
