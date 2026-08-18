{{--
    The mark for one scholarly or social profile.

    A full copy on purpose. This theme owns every file it renders, so deleting
    another theme cannot take this one down with it — there is no partial
    outside theme_diu/ that this page needs.

    It used to know six platforms — LinkedIn, GitHub, Facebook, Instagram,
    Google Scholar and ResearchGate — and handed everything else the same
    generic chain link. The seeded list is 25, and the researcher import writes
    ORCID, Scopus, Web of Science and Website, so a profile showed one
    recognisable icon and four identical links.

    The platforms carry an `icon_class` from the seeder, but it names Font
    Awesome and no theme loads it, so rendering those would show nothing at all.
    These are inline SVG like every other icon here.

    Brand marks where the platform has one people recognise. A lettermark where
    it does not — Scopus and DBLP have no glyph anybody would know on sight, and
    an invented one is worse than the letters, which is the reasoning this file
    already used for the "RG" it drew for ResearchGate.

    Expects: $platform — the platform display name.
--}}
@php
    $key = \Illuminate\Support\Str::of($platform ?? '')->lower()->trim()->replace([' ', '.', '(', ')'], '')->value();

    // Brand marks, drawn once. Anything not here falls to a lettermark below.
    $paths = [
        'github' => 'M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.7-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2 0-.4-.5-1.6.2-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.7 1.6.2 2.8.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .3z',
        'linkedin' => 'M20.4 20.4h-3.6v-5.6c0-1.3 0-3-1.9-3s-2.1 1.4-2.1 2.9v5.7H9.2V9h3.4v1.6h.1a3.8 3.8 0 0 1 3.4-1.9c3.6 0 4.3 2.4 4.3 5.5v6.2zM5.3 7.4a2.1 2.1 0 1 1 0-4.2 2.1 2.1 0 0 1 0 4.2zm1.8 13H3.5V9h3.6v11.4zM22.2 0H1.8C.8 0 0 .8 0 1.7v20.6c0 1 .8 1.7 1.8 1.7h20.4c1 0 1.8-.8 1.8-1.7V1.7c0-.9-.8-1.7-1.8-1.7z',
        'facebook' => 'M24 12.07A12 12 0 1 0 10.13 24v-8.44H7.08v-3.49h3.05V9.4c0-3.02 1.8-4.69 4.55-4.69 1.31 0 2.69.24 2.69.24v2.96h-1.52c-1.49 0-1.96.93-1.96 1.89v2.27h3.33l-.53 3.49h-2.8V24A12 12 0 0 0 24 12.07z',
        'instagram' => 'M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.89 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07c-4.35.2-6.78 2.62-6.98 6.98C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.2-4.35-2.62-6.78-6.98-6.98C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.4-11.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z',
        'youtube' => 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.08 0 12 0 12s0 3.92.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.92 24 12 24 12s0-3.92-.5-5.81zM9.55 15.57V8.43L15.82 12l-6.27 3.57z',
        'twitterx' => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.46l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41z',
        'medium' => 'M13.54 12a6.8 6.8 0 0 1-6.77 6.82A6.8 6.8 0 0 1 0 12a6.8 6.8 0 0 1 6.77-6.82A6.8 6.8 0 0 1 13.54 12zm7.42 0c0 3.54-1.51 6.42-3.38 6.42-1.87 0-3.39-2.88-3.39-6.42s1.52-6.42 3.39-6.42S20.96 8.46 20.96 12zM24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z',
        'kaggle' => 'M18.83 23.56c-.02.11-.14.2-.36.2h-3.24c-.24 0-.45-.1-.63-.32l-5.1-6.5-1.42 1.36v5.13c0 .22-.11.33-.33.33H5.22c-.22 0-.33-.11-.33-.33V.33c0-.22.11-.33.33-.33h2.53c.22 0 .33.11.33.33v14.13l6.16-6.23c.18-.18.4-.27.66-.27h3.35c.2 0 .32.09.36.26.04.16.02.28-.08.36l-6.5 6.28 6.74 8.34c.1.13.12.25.06.36z',
        'googlescholar' => 'M12 24a7 7 0 1 1 0-14 7 7 0 0 1 0 14zm0-24L0 9.5h4.09L12 3.32 19.91 9.5H24L12 0z',
        'orcid' => 'M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zM7.37 18.4H5.63V7.6h1.74v10.8zM6.5 6.68a1.01 1.01 0 1 1 0-2.02 1.01 1.01 0 0 1 0 2.02zm4.16.92h4.2c4 0 5.75 2.86 5.75 5.4 0 2.76-2.16 5.4-5.73 5.4h-4.22V7.6zm1.74 1.57v7.66h2.32c3.3 0 4.06-2.51 4.06-3.83 0-2.15-1.37-3.83-4.13-3.83h-2.25z',
        'website' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 2c.9 0 2.2 1.6 2.8 4.3H9.2C9.8 5.6 11.1 4 12 4zM8.8 10.3h6.4a17 17 0 0 1 0 3.4H8.8a17 17 0 0 1 0-3.4zM4.3 10.3h2.5a19 19 0 0 0 0 3.4H4.3a8 8 0 0 1 0-3.4zm12.9 0h2.5a8 8 0 0 1 0 3.4h-2.5a19 19 0 0 0 0-3.4zM12 20c-.9 0-2.2-1.6-2.8-4.3h5.6C14.2 18.4 12.9 20 12 20zm-6.7-4.3h2.2c.3 1.4.7 2.6 1.2 3.5a8 8 0 0 1-3.4-3.5zm10 0h2.2a8 8 0 0 1-3.4 3.5c.5-.9.9-2.1 1.2-3.5zM5.3 8.3a8 8 0 0 1 3.4-3.5c-.5.9-.9 2.1-1.2 3.5H5.3zm10 0c-.3-1.4-.7-2.6-1.2-3.5a8 8 0 0 1 3.4 3.5h-2.2z',
    ];

    // The rest are known by letters, with the colour doing the recognising.
    $letters = [
        'researchgate' => 'RG',
        'scopus' => 'Sc',
        'webofscience' => 'WoS',
        'ieeexplore' => 'IEEE',
        'ssrn' => 'SSRN',
        'academiaedu' => 'A',
        'semanticscholar' => 'S2',
        'loopfrontiers' => 'Loop',
        'dblp' => 'dblp',
        'arxiv' => 'aX',
        'philpeople' => 'Phil',
        'dimensions' => 'Di',
        'pubmed' => 'PM',
        'substack' => 'S',
    ];

    $colours = [
        'googlescholar' => '#4285F4', 'researchgate' => '#00CCBB', 'orcid' => '#A6CE39',
        'scopus' => '#E9711C', 'webofscience' => '#5E33BF', 'ieeexplore' => '#00629B',
        'ssrn' => '#1E4C7C', 'academiaedu' => '#41454A', 'semanticscholar' => '#1857B6',
        'loopfrontiers' => '#1A6BB5', 'dblp' => '#004F9F', 'arxiv' => '#B31B1B',
        'philpeople' => '#2A6496', 'dimensions' => '#45B02D', 'pubmed' => '#326295',
        'github' => '#181717', 'kaggle' => '#20BEFF', 'linkedin' => '#0A66C2',
        'twitterx' => '#0F1419', 'facebook' => '#1877F2', 'youtube' => '#FF0000',
        'instagram' => '#E4405F', 'medium' => '#000000', 'substack' => '#FF6719',
        'website' => '#475569',
    ];

    $colour = $colours[$key] ?? '#475569';
@endphp

@if(isset($paths[$key]))
    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" style="color: {{ $colour }}" aria-hidden="true">
        <path d="{{ $paths[$key] }}"/>
    </svg>
@elseif(isset($letters[$key]))
    {{-- Sized down as the word gets longer, so "IEEE" and "RG" occupy the same
         box as the drawn marks beside them. --}}
    <span class="font-sans font-black leading-none shrink-0 tracking-tight
        {{ strlen($letters[$key]) >= 4 ? 'text-[8px]' : (strlen($letters[$key]) === 3 ? 'text-[9px]' : 'text-[11px]') }}"
        style="color: {{ $colour }}" aria-hidden="true">{{ $letters[$key] }}</span>
@else
    {{-- Something seeded later, or a name nobody mapped. A chain link is honest
         about that; it was only wrong as the answer for four platforms at once,
         which is what it used to be. --}}
    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: {{ $colour }}" aria-hidden="true">
        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
    </svg>
@endif
