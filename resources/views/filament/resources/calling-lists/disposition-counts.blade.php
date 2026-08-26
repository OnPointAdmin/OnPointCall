<div>
    @if ($showHeading ?? true)
        <div style="font-size:0.875rem;font-weight:600;margin-bottom:0.5rem;">Disposition counts</div>
    @endif
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Disposition</th>
                <th style="text-align:right;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">Count</th>
                <th style="text-align:right;padding:0.4rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.3);font-weight:600;">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                @php
                    $isTotal = ($item['key'] ?? '') === 'total';
                    $percent = $item['percent'] ?? null;
                @endphp
                <tr style="{{ $isTotal ? 'font-weight:600;' : '' }}">
                    <td style="padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $item['label'] }}</td>
                    <td style="text-align:right;padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ number_format($item['count']) }}</td>
                    <td style="text-align:right;padding:0.35rem 0.75rem;border-bottom:1px solid rgba(128,128,128,0.18);">{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
