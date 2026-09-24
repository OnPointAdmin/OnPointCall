<div id="booking-callback-errors">
    <p style="margin:0 0 0.75rem;font-size:0.875rem;">
        @if ($schedule)
            Schedule:
            {{ $schedule->enabled ? \App\Support\Weekdays::labels($schedule->normalizedDaysOfWeek()) : 'Off' }}
            @if ($schedule->enabled)
                at {{ collect($schedule->normalizedRunTimes())->map(fn (string $time) => \Carbon\Carbon::parse($time)->format('g:i A'))->implode(', ') }}
            @endif
            ({{ $timezone }}).
        @else
            Schedule is not set up yet.
        @endif
    </p>

    @if (! $run)
        <p style="margin:0;font-size:0.875rem;">No imports yet. Use Import Agent Callbacks to pull Salesforce now.</p>
    @else
        <p style="margin:0 0 0.75rem;font-size:0.875rem;">
            Last run {{ \App\Support\CompanyTimezone::display($run->finished_at, $run->company_id) }}
            · created {{ $run->created_count }}
            · updated {{ $run->updated_count }}
            · closed {{ $run->closed_count }}
            · skipped {{ $run->skipped_no_phone_count + $run->skipped_dnc_terminal_count }}
            · {{ $run->error_count }} {{ $run->error_count === 1 ? 'error' : 'errors' }}
        </p>

        @if ($errors->isEmpty())
            <p style="margin:0;font-size:0.875rem;">No errors on the last run.</p>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Booking</th>
                        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Salesforce Id</th>
                        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Representative</th>
                        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Employee Id</th>
                        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($errors as $error)
                        <tr>
                            <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $error->booking_number ?: '—' }}</td>
                            <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $error->salesforce_booking_id ?: '—' }}</td>
                            <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $error->representative_name ?: '—' }}</td>
                            <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $error->employee_id ?: '—' }}</td>
                            <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $error->reason?->label() ?? $error->reason }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</div>
