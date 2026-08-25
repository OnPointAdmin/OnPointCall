<div style="display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:0.5rem;width:100%;">
    @foreach ($items as $item)
        <div style="display:inline-flex;align-items:center;gap:0.5rem;border-radius:0.5rem;padding:0.5rem 0.75rem;background:rgba(128,128,128,0.14);white-space:nowrap;">
            <span style="font-size:0.875rem;opacity:0.7;">{{ $item['label'] }}</span>
            <span style="font-size:0.875rem;font-weight:600;">{{ number_format($item['count']) }}</span>
        </div>
    @endforeach
</div>
