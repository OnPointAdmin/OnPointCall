<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Call Detail</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.4;">
    <h1 style="font-size: 20px; margin: 0 0 4px;">{{ $company->name }} — Call Detail</h1>
    <p style="color: #64748b; margin: 0 0 16px;">{{ $rangeLabel }} ({{ $periodLabel }})</p>

    <p style="margin: 0 0 8px;">
        {{ number_format($rowCount) }} {{ $rowCount === 1 ? 'call' : 'calls' }} in this range.
    </p>
    <p style="margin: 0;">
        The spreadsheet is attached as <strong>{{ $filename }}</strong>.
    </p>
</body>
</html>
