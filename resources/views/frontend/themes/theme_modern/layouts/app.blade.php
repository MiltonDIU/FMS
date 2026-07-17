<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    <script>{!! \App\Helpers\Appearance::preloadScript() !!}</script>
    @include('frontend.themes.theme_modern.partials.head')
    @vite([
        'resources/views/frontend/themes/theme_modern/assets/css/theme.css',
        'resources/views/frontend/themes/theme_modern/assets/js/theme.js',
    ])

    {!! \App\Helpers\FontManager::googleLinks('theme_modern') !!}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_modern') !!}
    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>
    {!! \App\Helpers\FontManager::cssBlock('theme_modern') !!}
</head>
<body class="bg-transparent min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    @include('frontend.themes.theme_modern.partials.header')

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
        @yield('content')
    </main>

    @include('frontend.themes.theme_modern.partials.footer')

    @livewireScripts
</body>
</html>
