@php
    /*
     * The masthead of a printed reference work: a thin line of small print, and
     * under it the name of the thing.
     *
     * The upper tier carries what a title page carries — where the institution
     * is, and how big this edition of the directory is. It is hidden below the
     * large breakpoint, where there is no room for small print and the three
     * counts are the first thing to go.
     *
     * $facultiesCount, $departmentsCount and $teachersCount arrive from the view
     * composer registered in AppServiceProvider for
     * frontend.themes.*.partials.header — which is why this file keeps its name
     * and its location.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
@endphp

<header class="masthead">

    {{-- Upper tier: the colophon line. --}}
    <div class="masthead-thin">
        <div class="sheet flex items-center justify-between gap-6 py-1.5">
            <p class="figure">{{ $brand['address_header'] }}</p>

            <p class="figure flex items-center gap-3">
                <span>{{ number_format($facultiesCount ?? 0) }} {{ $brand['stat_faculties_label'] }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ number_format($departmentsCount ?? 0) }} {{ $brand['stat_departments_label'] }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ number_format($teachersCount ?? 0) }} {{ $brand['stat_profiles_label'] }}</span>
            </p>
        </div>
    </div>

    {{-- Lower tier: the name, and the two controls anyone uses. --}}
    <div class="sheet">
        <div class="masthead-main">

            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 min-w-0"
               aria-label="{{ $brand['site_name'] }} — {{ $brand['badge_text'] }}">

                @if(! empty($brand['use_image_logo']) && ! empty($brand['logo_url']))
                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['site_name'] }}" class="h-8 w-auto shrink-0">
                @else
                    {{-- A solid ink square. The monogram is the only filled shape
                         in the theme, which is what makes it read as a mark. --}}
                    <span class="monogram" aria-hidden="true">{{ $brand['monogram'] }}</span>
                @endif

                <span class="min-w-0 flex items-baseline gap-2.5">
                    <span class="wordmark truncate">{{ $brand['site_short_name'] ?: $brand['short_name'] }}</span>
                    @if(filled($brand['badge_text']))
                        <span class="label hidden sm:inline">{{ $brand['badge_text'] }}</span>
                    @endif
                </span>
            </a>

            <div class="flex items-center gap-1.5 shrink-0">
                {{-- The label needs room a phone does not have, so below md the
                     same link becomes an icon. It used to be `hidden
                     md:inline-flex` and nothing else — the only navigation link
                     in the header, gone under 768px, and the footer does not
                     carry it either, so on a phone the university's own site was
                     unreachable from the directory. --}}
                @if(! empty($brand['main_site_url']))
                    <a href="{{ $brand['main_site_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="hidden md:inline-flex text-[13px] font-semibold px-2 py-1"
                       style="color: var(--ink-3);">{{ $brand['main_site_label'] }}</a>

                    <a href="{{ $brand['main_site_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="btn-icon md:hidden" aria-label="{{ $brand['main_site_label'] }}"
                       title="{{ $brand['main_site_label'] }}">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <path d="M15 3h6v6M10 14 21 3"/>
                        </svg>
                    </a>
                @endif

                <button type="button" id="appearance-toggle" class="btn-icon" aria-label="Toggle dark mode">
                    <svg class="w-[17px] h-[17px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="w-[17px] h-[17px] hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>
                    </svg>
                </button>

                @auth
                    <a href="{{ url('/admin') }}" class="btn btn-solid">Dashboard</a>
                @else
                    <a href="{{ url('/admin/login') }}" class="btn btn-solid">{{ $brand['login_label'] }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>
