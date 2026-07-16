<!DOCTYPE html>
<html lang="en">
<head>
    @include('frontend.themes.theme_default.partials.head')
    @vite([
        'resources/views/frontend/themes/theme_default/assets/css/theme.css',
        'resources/views/frontend/themes/theme_default/assets/js/theme.js',
    ])

    {!! \App\Helpers\FontManager::googleLinks('theme_default') !!}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_default') !!}
    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>
    {!! \App\Helpers\FontManager::cssBlock('theme_default') !!}
</head>
<body class="bg-transparent min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    @include('frontend.themes.theme_default.partials.header')

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
        @yield('content')
    </main>

    @include('frontend.themes.theme_default.partials.footer')

    @livewireScripts
</body>
</html>
