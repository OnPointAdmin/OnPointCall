<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Agent</title>
    <link rel="icon" href="{{ asset('images/onpoint-call.webp') }}" type="image/webp">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    x-data="{ dark: localStorage.getItem('opc-theme') === 'dark' }"
    x-init="$watch('dark', v => { localStorage.setItem('opc-theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v); }); document.documentElement.classList.toggle('dark', dark);"
    class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
>
    <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-6 py-3">
            <x-brand-mark size="sm" />
            <div class="flex items-center gap-3.5 text-sm">
                <span class="text-slate-500 dark:text-slate-400">{{ auth('agent')->user()->name }}</span>
                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 dark:bg-slate-950">
                    <button
                        type="button"
                        x-on:click="dark = false"
                        x-bind:class="!dark ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-transparent text-slate-400'"
                        class="flex items-center rounded-md border p-1.5"
                        aria-label="Light mode"
                    >
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" fill="currentColor"/><line x1="8" y1="0.5" x2="8" y2="2.3" stroke="currentColor" stroke-width="1.3"/><line x1="8" y1="13.7" x2="8" y2="15.5" stroke="currentColor" stroke-width="1.3"/><line x1="0.5" y1="8" x2="2.3" y2="8" stroke="currentColor" stroke-width="1.3"/><line x1="13.7" y1="8" x2="15.5" y2="8" stroke="currentColor" stroke-width="1.3"/><line x1="2.8" y1="2.8" x2="4.1" y2="4.1" stroke="currentColor" stroke-width="1.3"/><line x1="11.9" y1="11.9" x2="13.2" y2="13.2" stroke="currentColor" stroke-width="1.3"/><line x1="2.8" y1="13.2" x2="4.1" y2="11.9" stroke="currentColor" stroke-width="1.3"/><line x1="11.9" y1="4.1" x2="13.2" y2="2.8" stroke="currentColor" stroke-width="1.3"/></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="dark = true"
                        x-bind:class="dark ? 'border-blue-500 bg-blue-500/15 text-blue-400' : 'border-transparent text-slate-400'"
                        class="flex items-center rounded-md border p-1.5"
                        aria-label="Dark mode"
                    >
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" fill="currentColor"/><circle cx="11" cy="5.5" r="5" class="fill-white dark:fill-slate-900"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('agent.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1440px] px-6 py-5">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
