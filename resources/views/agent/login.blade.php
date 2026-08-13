@extends('agent.layouts.auth')

@section('title', 'Login')
@section('heading', 'Sign in')

@section('content')
    @if (session('status'))
        <div class="fi-fo-field">
            <p class="fi-sc-text" style="color: var(--success-400);">{{ session('status') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="fi-fo-field">
            <p class="fi-sc-text" style="color: var(--danger-400);">{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.login') }}" class="fi-sc fi-sc-has-gap" style="display:grid; gap:1.5rem;">
        @csrf

        <div data-field-wrapper class="fi-fo-field">
            <div class="fi-fo-field-label-col">
                <div class="fi-fo-field-label-ctn">
                    <label for="email" class="fi-fo-field-label">
                        <span class="fi-fo-field-label-content">
                            Email address<sup class="fi-fo-field-label-required-mark">*</sup>
                        </span>
                    </label>
                </div>
            </div>
            <div class="fi-fo-field-content-col">
                <x-filament::input.wrapper>
                    <x-filament::input
                        id="email"
                        name="email"
                        type="email"
                        :value="old('email')"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </x-filament::input.wrapper>
            </div>
        </div>

        <div data-field-wrapper class="fi-fo-field">
            <div class="fi-fo-field-label-col">
                <div class="fi-fo-field-label-ctn" style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; width:100%;">
                    <label for="password" class="fi-fo-field-label">
                        <span class="fi-fo-field-label-content">
                            Password<sup class="fi-fo-field-label-required-mark">*</sup>
                        </span>
                    </label>
                    <x-filament::link :href="route('agent.password.request')" tabindex="-1">
                        Forgot password?
                    </x-filament::link>
                </div>
            </div>
            <div class="fi-fo-field-content-col">
                <div
                    x-data="{ isPasswordRevealed: false }"
                    class="fi-fo-text-input fi-input-wrp"
                >
                    <div class="fi-input-wrp-content-ctn">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="fi-input"
                            x-bind:type="isPasswordRevealed ? 'text' : 'password'"
                        >
                    </div>
                    <div class="fi-input-wrp-suffix">
                        <div class="fi-input-wrp-actions">
                            <button
                                type="button"
                                x-on:click="isPasswordRevealed = ! isPasswordRevealed"
                                class="fi-icon-btn fi-size-sm fi-color-gray"
                                tabindex="-1"
                                :aria-label="isPasswordRevealed ? 'Hide password' : 'Show password'"
                            >
                                <svg x-show="! isPasswordRevealed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="fi-icon fi-size-md" style="width:1.25rem;height:1.25rem;">
                                    <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                    <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                </svg>
                                <svg x-cloak x-show="isPasswordRevealed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="fi-icon fi-size-md" style="width:1.25rem;height:1.25rem;">
                                    <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.186A10.004 10.004 0 0 0 10 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.374l1.091 1.091a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" />
                                    <path d="m10.748 13.93 2.523 2.523A9.956 9.956 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41a1.651 1.651 0 0 1 0-1.186A10.02 10.02 0 0 1 3.78 5.603l2.522 2.522a4 4 0 0 0 4.446 5.805Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div data-field-wrapper class="fi-fo-field">
            <label class="fi-fo-field-label" style="display:inline-flex; align-items:center; gap:0.625rem; cursor:pointer;">
                <x-filament::input.checkbox name="remember" value="1" />
                <span class="fi-fo-field-label-content">Remember me</span>
            </label>
        </div>

        <x-filament::button type="submit" class="fi-width-full" style="width:100%; color:#fff;">
            Sign in
        </x-filament::button>
    </form>
@endsection
