<?php

use App\Http\Controllers\Agent\AuthController;
use App\Http\Middleware\EnsureCanCall;
use App\Http\Middleware\SetCompanyContext;
use App\Livewire\Agent\Workspace;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('agent.login');
});

Route::get('/agent/login', [AuthController::class, 'showLogin'])->name('agent.login');
Route::post('/agent/login', [AuthController::class, 'login']);
Route::post('/agent/logout', [AuthController::class, 'logout'])->name('agent.logout');

Route::middleware(['auth', SetCompanyContext::class, EnsureCanCall::class])
    ->prefix('agent')
    ->group(function (): void {
        Route::get('/', Workspace::class)->name('agent.workspace');
    });
