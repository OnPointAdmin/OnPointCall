@extends('agent.layouts.auth')

@section('title', 'Choose destination')
@section('heading', 'Where would you like to go?')
@section('subheading', 'Admin is reporting and setup. Agent window is for taking calls.')

@section('content')
    <div class="fi-sc fi-sc-has-gap" style="display:grid; gap:1rem;">
        {{-- Plain anchors: Filament buttons use :hover, which iOS treats as the first tap. --}}
        <a
            href="{{ url('/admin') }}"
            style="display:block; width:100%; box-sizing:border-box; padding:0.875rem 1rem; border-radius:0.5rem; background:#2563eb; color:#fff; font-weight:500; font-size:0.875rem; line-height:1.25rem; text-align:center; text-decoration:none; -webkit-tap-highlight-color:rgba(255,255,255,0.2); touch-action:manipulation;"
        >
            Admin
        </a>

        <a
            href="{{ route('agent.workspace') }}"
            style="display:block; width:100%; box-sizing:border-box; padding:0.875rem 1rem; border-radius:0.5rem; background:transparent; color:#fff; font-weight:500; font-size:0.875rem; line-height:1.25rem; text-align:center; text-decoration:none; border:1px solid rgba(255,255,255,0.45); -webkit-tap-highlight-color:rgba(255,255,255,0.2); touch-action:manipulation;"
        >
            Agent window
        </a>
    </div>
@endsection
