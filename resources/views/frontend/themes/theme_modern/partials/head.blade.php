@php
    $brand = \App\Helpers\Branding::all();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="@yield('meta_description', $brand['meta_description'])">
<title>@yield('title', 'Faculty Directory' . $brand['meta_title_suffix'])</title>
