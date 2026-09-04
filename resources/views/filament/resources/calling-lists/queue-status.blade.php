<div>
    @if ($showHeading ?? true)
        <div style="font-size:0.875rem;font-weight:600;margin-bottom:0.5rem;">Queue status</div>
    @endif
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Status</th>
                <th style="text-align:right;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);{{ ($row['indent'] ?? false) ? 'padding-left:1.5rem;' : '' }}">
                        {{ $row['label'] }}
                        @if (! empty($row['timing']))
                            <div style="font-size:0.75rem;color:rgba(100,116,139,1);margin-top:0.15rem;">
                                {{ $row['timing'] }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align:right;padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);vertical-align:top;">
                        {{ number_format($row['count']) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($timezone ?? null)
        <p style="font-size:0.75rem;color:rgba(100,116,139,1);margin:0.5rem 0 0;">
            Times use {{ $timezone }}. Cadence timing follows each lead&apos;s local legal hours and day-part windows.
        </p>
    @endif
</div>
