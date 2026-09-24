<?php

namespace App\Filament\Resources\CallingLists\Actions;

use App\Models\CallingList;
use App\Services\Leads\AgentCallbacksProvisioner;
use App\Support\Weekdays;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class EditCallbackScheduleAction
{
    public static function make(): Action
    {
        return Action::make('editCallbackSchedule')
            ->label('Callback schedule')
            ->icon(Heroicon::OutlinedClock)
            ->fillForm(function (CallingList $record): array {
                $schedule = app(AgentCallbacksProvisioner::class)->scheduleFor($record->company_id);

                return [
                    'enabled' => $schedule->enabled,
                    'days_of_week' => $schedule->normalizedDaysOfWeek(),
                    'run_times' => $schedule->normalizedRunTimes(),
                ];
            })
            ->form([
                Toggle::make('enabled')
                    ->label('Run on a schedule')
                    ->default(true),
                Select::make('days_of_week')
                    ->label('Days')
                    ->multiple()
                    ->options(Weekdays::options())
                    ->default([1, 2, 3, 4, 5, 6, 7])
                    ->required(),
                Repeater::make('run_times')
                    ->label('Times')
                    ->simple(
                        TimePicker::make('time')
                            ->seconds(false)
                            ->required(),
                    )
                    ->minItems(1)
                    ->default(['07:00'])
                    ->addActionLabel('Add time'),
            ])
            ->action(function (CallingList $record, array $data): void {
                $schedule = app(AgentCallbacksProvisioner::class)->scheduleFor($record->company_id);
                $schedule->fill([
                    'enabled' => (bool) ($data['enabled'] ?? false),
                    'days_of_week' => $data['days_of_week'] ?? [],
                    'run_times' => $data['run_times'] ?? [],
                ])->save();

                Notification::make()
                    ->title('Callback schedule saved')
                    ->success()
                    ->send();
            });
    }
}
