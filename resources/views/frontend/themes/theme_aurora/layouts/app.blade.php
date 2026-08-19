<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    <script>{!! \App\Helpers\Appearance::preloadScript() !!}</script>
    @include('frontend.themes.theme_aurora.partials.head')
    @vite([
        'resources/views/frontend/themes/theme_aurora/assets/css/theme.css',
        'resources/views/frontend/themes/theme_aurora/assets/js/theme.js',
    ])

    {!! \App\Helpers\FontManager::googleLinks('theme_aurora') !!}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_aurora') !!}
    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>
    {!! \App\Helpers\FontManager::cssBlock('theme_aurora') !!}
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

    {{-- The aurora. A fixed element behind everything rather than a background
         on <body>, so the blur never rasterises the type above it and the light
         holds still while the page scrolls through it. aria-hidden because it
         is atmosphere and carries nothing to read. --}}
    <div class="aurora-field" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
    </div>

    @include('frontend.themes.theme_aurora.partials.header')

    <main class="flex-1 shell py-8 md:py-12">
        @yield('content')
    </main>

    @include('frontend.themes.theme_aurora.partials.footer')

    @livewireScripts
</body>
</html>
