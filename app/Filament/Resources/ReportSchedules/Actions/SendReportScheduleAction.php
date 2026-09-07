<?php

namespace App\Filament\Resources\ReportSchedules\Actions;

use App\Models\ReportSchedule;
use App\Services\Dashboard\ReportScheduleSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class SendReportScheduleAction
{
    public static function make(): Action
    {
        return Action::make('sendNow')
            ->label('Send now')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalHeading('Send this report now?')
            ->modalDescription('This sends immediately, ignoring the schedule’s days and times.')
            ->action(function (ReportSchedule $record, ReportScheduleSender $sender): void {
                $result = $sender->send($record, force: true);

                if ($result['sent']) {
                    Notification::make()
                        ->title('Report sent')
                        ->body($result['message'])
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($result['skipped'] ? 'Report not sent' : 'Send failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            });
    }
}
