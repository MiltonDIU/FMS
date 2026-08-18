@php
    /*
     * Renders the sharing and structured-data block for a public page.
     *
     * This theme's own copy. Every file this theme renders lives inside
     * theme_diu/, so removing another theme cannot take these pages down.
     *
     * The markup is deliberately the same in each theme: what a search engine
     * or a chat preview reads should not change with the skin. Keep them in
     * step by hand — that is the cost of the themes being independent, and it
     * is the trade that was chosen.
     *
     * Expects $seo from App\Helpers\SeoPayload.
     */
    $title = $seo['title'] ?? null;
    $description = $seo['description'] ?? null;
    $url = $seo['url'] ?? url()->current();
    $image = $seo['image'] ?? null;
    $type = $seo['type'] ?? 'website';
    $schema = $seo['schema'] ?? null;
    $siteName = \App\Helpers\Branding::get('site_name');
@endphp

{{-- No <meta name="description"> here: every theme's head partial already emits
     one from @yield('meta_description'), and the calling page fills that section
     from this same payload. Two of them would be a duplicate-content signal. --}}

{{-- OpenGraph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title }}">
@if(filled($description))
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $url }}">
@if($image)
    <meta property="og:image" content="{{ $image }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
@if(filled($description))
    <meta name="twitter:description" content="{{ $description }}">
@endif
@if($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif

@if($schema)
    {{-- JSON_HEX_TAG is what keeps this inside its own script element: names,
         titles and descriptions here are editable from the admin panel, and one
         containing a closing script tag would otherwise end the element and let
         what followed run as markup. The escaped form is still valid JSON-LD. --}}
    <script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endif
