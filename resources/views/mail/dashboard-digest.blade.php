<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Dashboard</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.5;">
    <h1 style="font-size: 20px;">{{ $company->name }} — Daily Summary</h1>
    <p style="color: #64748b;">{{ $day->format('l, F j, Y') }}</p>

    <h2 style="font-size: 16px; margin-top: 24px;">Totals</h2>
    <ul>
        <li><strong>Bookings:</strong> {{ $stats['bookings'] }}</li>
        <li><strong>Calls / dispositions:</strong> {{ $stats['calls'] }}</li>
        <li><strong>Skips:</strong> {{ $stats['skips'] }}</li>
        <li><strong>Overdue callbacks (now):</strong> {{ $overdueCallbacks }}</li>
    </ul>

    @if (count($agents) > 0)
        <h2 style="font-size: 16px; margin-top: 24px;">Per Agent</h2>
        <table style="border-collapse: collapse; width: 100%; max-width: 600px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #e2e8f0; padding: 8px; text-align: left;">Agent</th>
                    <th style="border: 1px solid #e2e8f0; padding: 8px;">Bookings</th>
                    <th style="border: 1px solid #e2e8f0; padding: 8px;">Calls</th>
                    <th style="border: 1px solid #e2e8f0; padding: 8px;">Skips</th>
                    <th style="border: 1px solid #e2e8f0; padding: 8px;">Callbacks pending</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $agent)
                    <tr>
                        <td style="border: 1px solid #e2e8f0; padding: 8px;">{{ $agent['name'] }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 8px; text-align: center;">{{ $agent['bookings'] }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 8px; text-align: center;">{{ $agent['calls'] }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 8px; text-align: center;">{{ $agent['skips'] }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 8px; text-align: center;">{{ $agent['callbacks_pending'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
