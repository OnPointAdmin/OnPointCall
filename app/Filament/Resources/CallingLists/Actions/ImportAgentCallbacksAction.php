<?php

namespace App\Filament\Resources\CallingLists\Actions;

use App\Filament\Resources\CallingLists\CallingListResource;
use App\Jobs\SyncBookingCallbacksJob;
use App\Models\CallingList;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\HtmlString;

class ImportAgentCallbacksAction
{
    public static function make(): Action
    {
        return Action::make('importAgentCallbacks')
            ->label('Import Agent Callbacks')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading('Import Agent Callbacks now?')
            ->modalDescription('Pulls open Salesforce Callback bookings onto this list and assigns them to the matching agent.')
            ->action(function (CallingList $record): void {
                try {
                    $result = Bus::dispatchNow(new SyncBookingCallbacksJob($record->company_id, false, 'import_now'));
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('Import failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                self::notify($record, $result);
            });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function notify(CallingList $record, array $result): void
    {
        if (! empty($result['refused']) || ! empty($result['failed'])) {
            Notification::make()
                ->title(! empty($result['refused']) ? 'Import already running' : 'Import failed')
                ->body((string) ($result['message'] ?? 'Import failed.'))
                ->danger()
                ->send();

            return;
        }

        $summary = sprintf(
            'Created %d, updated %d, closed %d, skipped %d.',
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['closed'] ?? 0,
            ($result['skipped_no_phone'] ?? 0) + ($result['skipped_dnc_terminal'] ?? 0),
        );

        $agentErrors = (int) ($result['agent_match_errors'] ?? 0);

        if ($agentErrors > 0) {
            $preview = collect($result['errors'] ?? [])
                ->filter(fn (array $error): bool => ($error['reason'] ?? '') !== 'no_callback_date')
                ->take(3)
                ->map(function (array $error): string {
                    $booking = $error['booking_number'] ?: ($error['salesforce_booking_id'] ?? 'Booking');
                    $who = $error['representative_name'] ?: ($error['employee_id'] ?? 'no representative');

                    return $booking.' ('.$who.')';
                })
                ->implode(', ');

            $url = CallingListResource::getUrl('view', ['record' => $record]).'#booking-callback-errors';

            Notification::make()
                ->title($agentErrors.' agent match errors')
                ->body(new HtmlString(e($summary.' '.$preview).'. <a href="'.e($url).'">View errors</a>'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Agent Callbacks imported')
            ->body($summary)
            ->success()
            ->send();
    }
}
