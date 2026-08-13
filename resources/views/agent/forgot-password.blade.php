@extends('agent.layouts.auth')

@section('title', 'Forgot password')
@section('heading', 'Forgot password?')
@section('subheading', 'Enter your email and we will send you a reset link.')

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

    <form method="POST" action="{{ route('agent.password.email') }}" style="display:grid; gap:1.5rem;">
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

        <x-filament::button type="submit" style="width:100%; color:#fff;">
            Send reset link
        </x-filament::button>
    </form>

    <div style="text-align:center;">
        <x-filament::link :href="route('agent.login')">
            Back to sign in
        </x-filament::link>
    </div>
@endsection
