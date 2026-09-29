<?php

namespace App\Filament\Support;

use App\Filament\Pages\ImportAgentCallbacks;
use App\Models\CallingList;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class BookingCallbackImportNotifier
{
    /**
     * @param  array<string, mixed>  $result
     */
    public static function notify(?CallingList $list, array $result): void
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

            $url = ImportAgentCallbacks::getUrl().'#booking-callback-errors';

            Notification::make()
                ->title($agentErrors.' agent match errors')
                ->body(new HtmlString(e($summary.' '.$preview).'. <a href="'.e($url).'">View errors</a>'))
                ->danger()
                ->send();

            return;
        }

        $title = $list
            ? 'Agent Callbacks imported to '.$list->name
            : 'Agent Callbacks imported';

        Notification::make()
            ->title($title)
            ->body($summary)
            ->success()
            ->send();
    }
}
