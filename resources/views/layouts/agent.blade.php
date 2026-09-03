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
    x-data="{ dark: localStorage.getItem('opc-theme') === 'dark', userMenuOpen: false }"
    x-init="$watch('dark', v => { localStorage.setItem('opc-theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v); }); document.documentElement.classList.toggle('dark', dark);"
    class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
>
    <header class="relative z-50 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-6 py-3">
            <x-brand-mark size="sm" />
            <div class="flex items-center gap-3.5 text-sm">
                @if (auth('agent')->user()?->role->canAccessAdmin())
                    <a
                        href="{{ url('/admin') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-md px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    >
                        Admin
                    </a>
                @endif
                <div class="relative" x-on:click.outside="userMenuOpen = false" x-on:keydown.escape.window="userMenuOpen = false">
                    <button
                        type="button"
                        x-on:click="userMenuOpen = ! userMenuOpen"
                        x-bind:aria-expanded="userMenuOpen"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                        aria-haspopup="menu"
                    >
                        <span>{{ auth('agent')->user()->name }}</span>
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-cloak
                        x-show="userMenuOpen"
                        x-transition
                        class="absolute right-0 z-50 mt-1 w-48 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                        role="menu"
                    >
                        <form method="POST" action="{{ route('agent.logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                                role="menuitem"
                            >
                                Sign out
                            </button>
                        </form>
                        <a
                            href="{{ route('agent.password.change') }}"
                            class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                            role="menuitem"
                        >
                            Change password
                        </a>
                    </div>
                </div>
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
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1440px] px-6 py-5">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif
        {{ $slot }}
    </main>

    @livewireScripts
    <script>
        window.opcCopyPhone = async function (phone, manualDialOnly, hint) {
            if (! phone) {
                return false;
            }
            if (window.innerWidth < 768 && ! manualDialOnly) {
                return false;
            }

            const fallbackCopy = () => {
                const textarea = document.createElement('textarea');
                textarea.value = phone;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            };

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(phone);
                } else {
                    fallbackCopy();
                }
            } catch (error) {
                fallbackCopy();
            }

            if (hint) {
                const original = hint.dataset.originalText || hint.textContent;
                hint.dataset.originalText = original;
                hint.textContent = 'Copied!';
                setTimeout(() => {
                    hint.textContent = original;
                }, 2000);
            }

            return true;
        };
    </script>
</body>
</html>
