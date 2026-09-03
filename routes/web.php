<?php

use App\Http\Controllers\Agent\AuthController;
use App\Http\Controllers\Agent\ChangePasswordController;
use App\Http\Controllers\Agent\PasswordResetController;
use App\Http\Middleware\EnsureCanCall;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SetCompanyContext;
use App\Livewire\Agent\Workspace;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/choose', [AuthController::class, 'showChoose'])->name('choose');

Route::redirect('/agent/login', '/');
Route::redirect('/admin/login', '/');

Route::post('/agent/logout', [AuthController::class, 'logout'])->name('agent.logout');

Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::redirect('/agent/forgot-password', '/forgot-password');
Route::redirect('/admin/password-reset/request', '/forgot-password');

Route::get('/agent/reset-password/{token}', [PasswordResetController::class, 'showResetPassword'])->name('agent.password.reset');
Route::post('/agent/reset-password', [PasswordResetController::class, 'resetPassword'])->name('agent.password.update');

Route::middleware(['auth:agent', SetCompanyContext::class])
    ->prefix('agent')
    ->group(function (): void {
        Route::get('/change-password', [ChangePasswordController::class, 'show'])->name('agent.password.change');
        Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('agent.password.change.update');

        Route::middleware([EnsureCanCall::class, EnsurePasswordChanged::class])->group(function (): void {
            Route::get('/', Workspace::class)->name('agent.workspace');
        });
    });
