<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Performance by Lead Source</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.4;">
    @php
        $cell = 'border: 1px solid #e2e8f0; padding: 6px 8px; font-size: 12px; white-space: nowrap;';
        $head = $cell.' background: #f8fafc; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em;';
        $left = $cell.' text-align: left; font-weight: 700;';
        $center = $cell.' text-align: center;';
        $muted = $center.' color: #64748b;';
        $showsVenue = $groupBy->showsVenue();
        $showsEvent = $groupBy->showsEvent();
    @endphp

    <h1 style="font-size: 20px; margin: 0 0 4px;">{{ $company->name }} — Performance by Lead Source</h1>
    <p style="color: #64748b; margin: 0 0 20px;">{{ $rangeLabel }} ({{ $periodLabel }})</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">Totals</h2>
    <table style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="{{ $head }} text-align: left;">Metric</th>
                <th style="{{ $head }}">Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $left }}">Total Leads Called</td>
                <td style="{{ $center }}">{{ number_format($totals['total_leads_called'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Booked</td>
                <td style="{{ $center }}">{{ number_format($totals['booked'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Booked %</td>
                <td style="{{ $muted }}">{{ $leadSource->formatPercent($totals['booked_percent'] ?? null) }}</td>
            </tr>
        </tbody>
    </table>
    <p style="color: #64748b; font-size: 12px; margin: -12px 0 24px;">Booked % is the share of total leads called that were booked.</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">{{ $groupBy->tableTitle() }}</h2>
    @if (count($rows) > 0)
        <table style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    @if ($showsVenue)
                        <th style="{{ $head }} text-align: left;">Venue</th>
                    @endif
                    @if ($showsEvent)
                        <th style="{{ $head }} text-align: left;">Event</th>
                    @endif
                    <th style="{{ $head }}">Total Leads Called</th>
                    <th style="{{ $head }}">Booked</th>
                    <th style="{{ $head }}">Booked %</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @if ($showsVenue)
                            <td style="{{ $left }}">{{ $row['venue'] }}</td>
                        @endif
                        @if ($showsEvent)
                            <td style="{{ $left }}">{{ $row['event'] }}</td>
                        @endif
                        <td style="{{ $center }}">{{ number_format($row['total_leads_called']) }}</td>
                        <td style="{{ $center }}">{{ number_format($row['booked']) }}</td>
                        <td style="{{ $muted }}">{{ $leadSource->formatPercent($row['booked_percent'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #64748b;">No activity for this period.</p>
    @endif
</body>
</html>
