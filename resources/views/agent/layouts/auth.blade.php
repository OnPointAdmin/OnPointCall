@php
    use Filament\Support\Colors\Color;

    filament()->setCurrentPanel('admin');
    filament()->bootCurrentPanel();

    $primaryPalette = Color::Blue;
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="fi dark"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/onpoint-call.webp') }}" type="image/webp">

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @filamentStyles
    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontPreloadHtml() }}
    {{ filament()->getFontHtml() }}

    <style>
        :root {
            --font-family: '{!! filament()->getFontFamily() !!}';
            --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};
            @foreach ($primaryPalette as $shade => $color)
                --primary-{{ $shade }}: {{ $color }};
            @endforeach
        }
    </style>

    <script>
        document.documentElement.classList.add('dark')
        localStorage.setItem('theme', 'dark')
    </script>
</head>
<body class="fi-body fi-panel-admin">
    <div class="fi-simple-layout">
        <div class="fi-simple-main-ctn">
            <main class="fi-simple-main fi-width-lg">
                <div class="fi-simple-page">
                    <div class="fi-simple-page-content">
                        <header class="fi-simple-header">
                            <div class="fi-logo" style="height: 3.75rem;">
                                <x-brand-mark size="lg" :fill="true" />
                            </div>
                            <h1 class="fi-simple-header-heading" style="color: #fff;">
                                @yield('heading')
                            </h1>
                            @hasSection('subheading')
                                <p class="fi-simple-header-subheading">
                                    @yield('subheading')
                                </p>
                            @endif
                        </header>

                        @yield('content')
                    </div>
                </div>
            </main>
        </div>
    </div>

    @filamentScripts(withCore: true)
</body>
</html>
