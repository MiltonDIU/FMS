<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    <script>{!! \App\Helpers\Appearance::preloadScript() !!}</script>
    @include('frontend.themes.theme_portrait.partials.head')
    @vite([
        'resources/views/frontend/themes/theme_portrait/assets/css/theme.css',
        'resources/views/frontend/themes/theme_portrait/assets/js/theme.js',
    ])

    {!! \App\Helpers\FontManager::googleLinks('theme_portrait') !!}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_portrait') !!}
    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>
    {!! \App\Helpers\FontManager::cssBlock('theme_portrait') !!}
</head>
{{-- Wider and quieter than the other themes: the gallery needs the width, and
     a plain ground keeps the photographs the only saturated thing on screen. --}}
<body class="bg-surface-page min-h-screen flex flex-col font-sans antialiased"
      style="color: var(--text-soft);">

    @include('frontend.themes.theme_portrait.partials.header')

    <main class="flex-1 w-full max-w-[88rem] mx-auto px-5 sm:px-8 py-10 md:py-14">
        @yield('content')
    </main>

    @include('frontend.themes.theme_portrait.partials.footer')

    @livewireScripts
</body>
</html>
