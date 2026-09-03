@extends('agent.layouts.auth')

@section('title', 'Choose destination')
@section('heading', 'Where would you like to go?')

@section('content')
    <div class="fi-sc fi-sc-has-gap" style="display:grid; gap:1rem;">
        <x-filament::button
            tag="a"
            :href="url('/admin')"
            class="fi-width-full"
            style="width:100%; color:#fff; text-align:center;"
        >
            Admin
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="route('agent.workspace')"
            color="gray"
            class="fi-width-full"
            style="width:100%; text-align:center;"
        >
            Agent window
        </x-filament::button>
    </div>
@endsection
