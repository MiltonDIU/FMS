<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    <script>{!! \App\Helpers\Appearance::preloadScript() !!}</script>
    @include('frontend.themes.theme_ledger.partials.head')
    @vite([
        'resources/views/frontend/themes/theme_ledger/assets/css/theme.css',
        'resources/views/frontend/themes/theme_ledger/assets/js/theme.js',
    ])

    {!! \App\Helpers\FontManager::googleLinks('theme_ledger') !!}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_ledger') !!}
    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>
    {!! \App\Helpers\FontManager::cssBlock('theme_ledger') !!}
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

    @include('frontend.themes.theme_ledger.partials.header')

    {{-- No decorative band, no hero panel: the page begins where the content
         begins. The masthead's double rule is the only thing between them. --}}
    <main class="flex-1 sheet py-8 md:py-12">
        @yield('content')
    </main>

    @include('frontend.themes.theme_ledger.partials.footer')

    @livewireScripts
</body>
</html>
