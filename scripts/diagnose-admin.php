<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = User::withoutGlobalScopes()->where('email', 'jason.paine@onpointcall.com')->first();

if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "User: {$user->email} id={$user->id} role={$user->role->value} active=".($user->active ? 'yes' : 'no')."\n";
echo "Password check: ".(Hash::check('password', $user->password) ? 'OK' : 'FAIL')."\n";
echo "canAccessPanel: ".($user->canAccessPanel(filament()->getCurrentOrDefaultPanel()) ? 'yes' : 'no')."\n";

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setUserResolver(fn () => $user);

try {
    $start = microtime(true);
    $response = $app->handle($request);
    $elapsed = round(microtime(true) - $start, 2);
    echo "Dashboard status: {$response->getStatusCode()} ({$elapsed}s)\n";

    if ($response->getStatusCode() >= 400) {
        echo substr($response->getContent(), 0, 2000)."\n";
    }
} catch (Throwable $e) {
    echo 'Exception: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
