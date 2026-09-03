<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Dashboard</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.4;">
    @php
        $cell = 'border: 1px solid #e2e8f0; padding: 6px 8px; font-size: 12px; white-space: nowrap;';
        $head = $cell.' background: #f8fafc; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em;';
        $left = $cell.' text-align: left; font-weight: 700;';
        $center = $cell.' text-align: center;';
        $muted = $center.' color: #64748b;';
        $listLeft = $cell.' text-align: left; font-weight: 500; padding-left: 20px; background: #f8fafc; color: #475569;';
        $listCell = $center.' background: #f8fafc;';
        $listMuted = $muted.' background: #f8fafc;';
        $totalCell = $cell.' background: #1e3a5f; color: #fff; font-weight: 800; text-align: center;';
        $totalLeft = $cell.' background: #1e3a5f; color: #fff; font-weight: 800; text-align: left;';
    @endphp

    <h1 style="font-size: 20px; margin: 0 0 4px;">{{ $company->name }} — Daily Summary</h1>
    <p style="color: #64748b; margin: 0 0 20px;">{{ $day->format('l, F j, Y') }}</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">Totals</h2>
    <table style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="{{ $head }} text-align: left;">Metric</th>
                <th style="{{ $head }}">Count</th>
                <th style="{{ $head }}">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($metricDefinitions as $definition)
                @php
                    $metric = $totals[$definition['key']] ?? ['count' => 0, 'percent' => null];
                @endphp
                <tr>
                    <td style="{{ $left }}">{{ $definition['label'] }}</td>
                    <td style="{{ $center }}">{{ number_format($metric['count'] ?? 0) }}</td>
                    <td style="{{ $muted }}">
                        @if ($definition['show_percent'])
                            {{ $dashboard->formatPercent($totals, $definition['key']) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="color: #64748b; font-size: 12px; margin: -12px 0 24px;">Percentages represent % of total leads called.</p>

    <h2 style="font-size: 16px; margin: 0 0 8px;">Results by Rep</h2>
    @if (count($agents) > 0)
        <table style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th rowspan="2" style="{{ $head }} text-align: left;">Rep</th>
                    <th rowspan="2" style="{{ $head }}">Total Leads Called</th>
                    @foreach ($metricDefinitions as $definition)
                        @continue($definition['key'] === 'total_leads_called')
                        <th colspan="2" style="{{ $head }}">{{ $definition['label'] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($metricDefinitions as $definition)
                        @continue($definition['key'] === 'total_leads_called')
                        <th style="{{ $head }}">#</th>
                        <th style="{{ $head }}">%</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $agent)
                    @php
                        $agentLists = $agent['lists'] ?? [];
                        $showLists = count($agentLists) > 1;
                    @endphp
                    <tr>
                        <td style="{{ $left }}">{{ $agent['name'] }}</td>
                        <td style="{{ $center }}">{{ number_format($agent['metrics']['total_leads_called']['count'] ?? 0) }}</td>
                        @foreach ($metricDefinitions as $definition)
                            @continue($definition['key'] === 'total_leads_called')
                            @php
                                $metric = $agent['metrics'][$definition['key']] ?? ['count' => 0, 'percent' => null];
                            @endphp
                            <td style="{{ $center }}">{{ number_format($metric['count'] ?? 0) }}</td>
                            <td style="{{ $muted }}">{{ $dashboard->formatPercent($agent['metrics'], $definition['key']) }}</td>
                        @endforeach
                    </tr>
                    @if ($showLists)
                        @foreach ($agentLists as $list)
                            <tr>
                                <td style="{{ $listLeft }}">{{ $list['name'] }}</td>
                                <td style="{{ $listCell }}">{{ number_format($list['metrics']['total_leads_called']['count'] ?? 0) }}</td>
                                @foreach ($metricDefinitions as $definition)
                                    @continue($definition['key'] === 'total_leads_called')
                                    @php
                                        $metric = $list['metrics'][$definition['key']] ?? ['count' => 0, 'percent' => null];
                                    @endphp
                                    <td style="{{ $listCell }}">{{ number_format($metric['count'] ?? 0) }}</td>
                                    <td style="{{ $listMuted }}">{{ $dashboard->formatPercent($list['metrics'], $definition['key']) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                <tr>
                    <td style="{{ $totalLeft }}">Total</td>
                    <td style="{{ $totalCell }}">{{ number_format($totals['total_leads_called']['count'] ?? 0) }}</td>
                    @foreach ($metricDefinitions as $definition)
                        @continue($definition['key'] === 'total_leads_called')
                        @php
                            $metric = $totals[$definition['key']] ?? ['count' => 0, 'percent' => null];
                        @endphp
                        <td style="{{ $totalCell }}">{{ number_format($metric['count'] ?? 0) }}</td>
                        <td style="{{ $totalCell }}">{{ $dashboard->formatPercent($totals, $definition['key']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        <p style="color: #64748b; font-size: 12px; margin: 8px 0 0;">Percentages represent % of total leads called.</p>
    @else
        <p style="color: #64748b;">No activity for this day.</p>
    @endif
</body>
</html>
