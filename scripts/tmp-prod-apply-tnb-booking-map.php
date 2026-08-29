<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$map = [
    '2ff7-7114-0d49' => 'external_lead_id',
    'f776-d580-398a' => 'first_name',
    '0a4f-9d93-87f3' => 'last_name',
    'd64f-4a22-9c19' => 'phone',
    'e668-b01b-2857' => 'email',
    '6a99-2204-4c7c' => 'address',
    '49cc-f3e9-d39e' => 'address_2',
    '2861-1af5-2ebb' => 'city',
    'd995-a548-5acd' => 'state',
    'ff18-88a3-11df' => 'zip',
    '9fe5-80b8-65d6' => 'age_range',
    '9edb-f4b6-5970' => 'annual_income',
    '4d26-9cb2-0a61' => 'marital_status',
    'fd65-8e57-7f36' => 'gender',
    '24c0-a88e-ff42' => 'home_owner',
    'a4e4-0997-7dd4' => 'soft_score_code',
];

$settings = App\Models\AppSetting::withoutGlobalScopes()->get();

if ($settings->isEmpty()) {
    fwrite(STDERR, "No app_settings rows found.\n");
    exit(1);
}

foreach ($settings as $row) {
    $row->update(['booking_param_map' => $map]);
    echo 'settings id='.$row->id
        .' url='.($row->booking_url_template ?: '(empty)')
        .' keys='.count($row->fresh()->booking_param_map)
        .PHP_EOL;
}

$lists = App\Models\CallingList::withoutGlobalScopes()->orderBy('id')->get();

foreach ($lists as $list) {
    $list->update(['booking_param_map' => []]);
    echo 'cleared list id='.$list->id.' name='.$list->name.PHP_EOL;
}
