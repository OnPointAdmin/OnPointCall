<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$lead = App\Models\Lead::withoutGlobalScopes()
    ->where('phone', '5166956824')
    ->orWhere('phone_2', '5166956824')
    ->first();

if (! $lead) {
    fwrite(STDERR, "Lead not found.\n");
    exit(1);
}

echo 'lead_id='.$lead->id.PHP_EOL;
echo 'phone='.$lead->phone.PHP_EOL;
echo 'email='.($lead->email ?: '(empty)').PHP_EOL;
echo 'list_id='.($lead->calling_list_id ?: '(none)').PHP_EOL;
echo 'status='.$lead->status?->value.PHP_EOL;

try {
    $url = app(App\Services\Leads\BookingUrlBuilder::class)->build($lead);
    echo 'url='.($url ?: '(null)').PHP_EOL;
} catch (Throwable $e) {
    echo 'BUILD_ERROR='.$e::class.': '.$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
    exit(1);
}
