@php
    // Shared SEO partial: OpenGraph / Twitter tags + Schema.org Person JSON-LD.
    // Used by teacher profile pages (and reusable for department/faculty later).
    $brand = \App\Helpers\Branding::all();
    $siteName = $brand['site_name'] ?? 'Faculty Directory';

    $name = $metaTitle ?? ($brand['meta_description'] ?? $siteName);
    $description = $metaDescription ?? $brand['meta_description'];
    $image = $photoUrl ?? $brand['logo_url'];
    $url = $profileUrl ?? request()->url();

    // Schema.org Person payload.
    $person = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => trim(($teacher->first_name ?? '') . ' ' . ($teacher->middle_name ?? '') . ' ' . ($teacher->last_name ?? '')),
        'url' => $url,
    ];
    if ($image) {
        $person['image'] = $image;
    }
    if ($teacher->designation?->name) {
        $person['jobTitle'] = $teacher->designation->name;
    }
    if ($teacher->secondary_email) {
        $person['email'] = $teacher->secondary_email;
    }
    if ($teacher->phone) {
        $person['telephone'] = $teacher->phone;
    }
    if ($teacher->bio || $teacher->research_interest) {
        $person['description'] = trim(strip_tags($teacher->bio ?: $teacher->research_interest));
    }
    $affiliation = ['@type' => 'EducationalOrganization', 'name' => $siteName];
    if (! empty($department->name)) {
        $affiliation['department'] = $department->name;
    }
    if (! empty($faculty->name)) {
        $affiliation['parentOrganization'] = $faculty->name;
    }
    $person['affiliation'] = $affiliation;
    $sameAs = $teacher->socialLinks?->pluck('url')->filter()->values()->toArray() ?? [];
    if (! empty($sameAs)) {
        $person['sameAs'] = $sameAs;
    }
@endphp

{{-- OpenGraph --}}
<meta property="og:type" content="profile">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $name }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $url }}">
@if($image)
<meta property="og:image" content="{{ $image }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $name }}">
<meta name="twitter:description" content="{{ $description }}">
@if($image)
<meta name="twitter:image" content="{{ $image }}">
@endif

{{-- Schema.org Person --}}
<script type="application/ld+json">
{!! json_encode($person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
