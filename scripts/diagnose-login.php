<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'jason.paine@onpointmrg.com';
$user = User::withoutGlobalScopes()->where('email', $email)->first();

if (! $user) {
    echo "User not found: {$email}\n";
    exit(1);
}

echo "User: {$user->email}\n";
echo "Role: {$user->role->value}\n";
echo "Active: ".($user->active ? 'yes' : 'no')."\n";
echo "List assignments: ".$user->listAssignments()->count()."\n";
echo "canCall: ".($user->canCall() ? 'yes' : 'no')."\n";
echo "canAccessPanel: ".($user->canAccessPanel(filament()->getCurrentOrDefaultPanel()) ? 'yes' : 'no')."\n";
echo "Password 'password': ".(Hash::check('password', $user->password) ? 'OK' : 'FAIL')."\n";
echo "Auth::attempt password: ".(Auth::attempt(['email' => $email, 'password' => 'password']) ? 'OK' : 'FAIL')."\n";

// Test Filament login POST
$login = Illuminate\Http\Request::create('/admin/login', 'POST', [
    'email' => $email,
    'password' => 'password',
]);
$login->headers->set('Accept', 'text/html');

try {
    $response = $app->handle($login);
    echo "POST /admin/login status: ".$response->getStatusCode()."\n";
} catch (Throwable $e) {
    echo 'POST /admin/login error: '.$e->getMessage()."\n";
}

// Test agent login POST
$agentLogin = Illuminate\Http\Request::create('/agent/login', 'POST', [
    'email' => $email,
    'password' => 'password',
]);
try {
    $response = $app->handle($agentLogin);
    echo "POST /agent/login status: ".$response->getStatusCode()."\n";
    if ($response->isRedirect()) {
        echo "Redirect: ".$response->headers->get('Location')."\n";
    }
} catch (Throwable $e) {
    echo 'POST /agent/login error: '.$e->getMessage()."\n";
}
