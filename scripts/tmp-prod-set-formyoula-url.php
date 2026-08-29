<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = App\Support\BookingParamMap::FORM_URL;

$updated = App\Models\AppSetting::withoutGlobalScopes()->get();

if ($updated->isEmpty()) {
    fwrite(STDERR, "No app_settings rows found.\n");
    exit(1);
}

foreach ($updated as $row) {
    $row->update(['booking_url_template' => $url]);
    echo 'settings id='.$row->id.' url='.$row->fresh()->booking_url_template.PHP_EOL;
}

$lead = App\Models\Lead::withoutGlobalScopes()->where('phone', '5166956824')->first();

if ($lead) {
    echo 'lead_url='.app(App\Services\Leads\BookingUrlBuilder::class)->build($lead).PHP_EOL;
}
