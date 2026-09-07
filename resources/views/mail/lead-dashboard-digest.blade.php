<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lead Dashboard</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.4;">
    @php
        $cell = 'border: 1px solid #e2e8f0; padding: 6px 8px; font-size: 12px; white-space: nowrap;';
        $head = $cell.' background: #f8fafc; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em;';
        $left = $cell.' text-align: left; font-weight: 700;';
        $center = $cell.' text-align: center;';
        $muted = $center.' color: #64748b;';
    @endphp

    <h1 style="font-size: 20px; margin: 0 0 4px;">{{ $company->name }} — Lead Dashboard</h1>
    <p style="color: #64748b; margin: 0 0 20px;">As of {{ $snapshot->runAt }} {{ $snapshot->timezone }}</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">Lead status</h2>
    <table style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="{{ $head }} text-align: left;">Metric</th>
                <th style="{{ $head }}">Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $left }}">Total leads</td>
                <td style="{{ $center }}">{{ number_format($snapshot->total) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Fresh</td>
                <td style="{{ $center }}">{{ number_format($snapshot->fresh) }}</td>
            </tr>
            @foreach ($statusOrder as $status)
                <tr>
                    <td style="{{ $left }}">{{ $statusLabels[$status] ?? $status }}</td>
                    <td style="{{ $center }}">{{ number_format($snapshot->statusCounts[$status] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 style="font-size: 16px; margin: 0 0 8px;">Dialable now</h2>
    <table style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="{{ $head }} text-align: left;">Metric</th>
                <th style="{{ $head }}">Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $left }}">Ready now</td>
                <td style="{{ $center }}">{{ number_format($snapshot->readyNow) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Waiting</td>
                <td style="{{ $center }}">{{ number_format($snapshot->waiting) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Claimed</td>
                <td style="{{ $center }}">{{ number_format($snapshot->claimed) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Exhausted</td>
                <td style="{{ $center }}">{{ number_format($snapshot->exhausted) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Callbacks due</td>
                <td style="{{ $center }}">{{ number_format($snapshot->callbacksDue) }}</td>
            </tr>
            <tr>
                <td style="{{ $left }}">Callbacks scheduled</td>
                <td style="{{ $center }}">{{ number_format($snapshot->callbacksScheduled) }}</td>
            </tr>
        </tbody>
    </table>

    <h2 style="font-size: 16px; margin: 0 0 8px;">When leads become dialable</h2>
    <table style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="{{ $head }} text-align: left;">Window</th>
                <th style="{{ $head }}">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($snapshot->forecast as $bucket)
                <tr>
                    <td style="{{ $left }}">{{ $bucket['label'] }}</td>
                    <td style="{{ $center }}">{{ number_format($bucket['count']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="color: #64748b; font-size: 12px; margin: -12px 0 24px;">Forecast is for the callable pool only. Times use {{ $snapshot->timezone }}.</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">By calling list</h2>
    @if (count($snapshot->byList) > 0)
        <table style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="{{ $head }} text-align: left;">List</th>
                    <th style="{{ $head }}">Total</th>
                    <th style="{{ $head }}">Holding</th>
                    <th style="{{ $head }}">Ready now</th>
                    <th style="{{ $head }}">Waiting</th>
                    <th style="{{ $head }}">Callbacks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($snapshot->byList as $row)
                    <tr>
                        <td style="{{ $left }}">{{ $row['name'] }}</td>
                        <td style="{{ $center }}">{{ number_format($row['total']) }}</td>
                        <td style="{{ $center }}">{{ number_format($row['holding']) }}</td>
                        <td style="{{ $center }}">{{ number_format($row['ready_now']) }}</td>
                        <td style="{{ $center }}">{{ number_format($row['waiting']) }}</td>
                        <td style="{{ $center }}">{{ number_format($row['callbacks']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #64748b;">No calling lists yet.</p>
    @endif
</body>
</html>
