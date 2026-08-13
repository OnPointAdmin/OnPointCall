<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = config('services.soft_score.client_id');
$secret = config('services.soft_score.client_secret');
$base = rtrim((string) config('services.soft_score.base_url'), '/');

echo 'config_id=' . ($id ? '[SET]' : '[EMPTY]') . PHP_EOL;
echo 'config_secret=' . ($secret ? '[SET]' : '[EMPTY]') . PHP_EOL;
echo 'config_base=' . $base . PHP_EOL;

if (! $id || ! $secret) {
    fwrite(STDERR, "credentials missing\n");
    exit(1);
}

$response = Illuminate\Support\Facades\Http::asForm()
    ->timeout(15)
    ->post("{$base}/oauth/v2/accesstoken?grant_type=client_credentials", [
        'client_id' => $id,
        'client_secret' => $secret,
    ]);

echo 'token_http=' . $response->status() . PHP_EOL;
echo 'token_ok=' . ($response->successful() && is_string($response->json('access_token')) ? 'yes' : 'no') . PHP_EOL;
if (! $response->successful()) {
    echo 'body=' . substr($response->body(), 0, 200) . PHP_EOL;
}
