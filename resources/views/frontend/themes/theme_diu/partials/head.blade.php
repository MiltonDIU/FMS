<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="@yield('meta_description', \App\Helpers\Branding::get('meta_description'))">
<title>@yield('title', 'Faculty Directory' . \App\Helpers\Branding::get('meta_title_suffix'))</title>
@yield('seo')

{{-- Fonts are emitted by FontManager::googleLinks() in the layout, driven by
     the per-theme font pickers in System Settings. Do not hardcode them here. --}}
