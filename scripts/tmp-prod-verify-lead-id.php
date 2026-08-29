<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$builder = app(App\Services\Leads\BookingUrlBuilder::class);

$frankel = App\Models\Lead::withoutGlobalScopes()->where('phone', '5166956824')->first();
$tnbLead = App\Models\Lead::withoutGlobalScopes()
    ->where('lead_type', 'tnb')
    ->where('external_lead_id', 'like', '00Q%')
    ->first();

echo 'frankel_has_find='.(str_contains((string) $builder->build($frankel), '2ff7-7114-0d49=') ? 'yes' : 'no').PHP_EOL;
echo 'frankel_url='.$builder->build($frankel).PHP_EOL;

if ($tnbLead) {
    $url = $builder->build($tnbLead);
    echo 'tnb00q_id='.$tnbLead->external_lead_id.PHP_EOL;
    echo 'tnb00q_has_find='.(str_contains((string) $url, '2ff7-7114-0d49='.$tnbLead->external_lead_id) ? 'yes' : 'no').PHP_EOL;
}
