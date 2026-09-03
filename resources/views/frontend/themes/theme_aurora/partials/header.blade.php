@php
    /*
     * A slim glass bar floating on the aurora.
     *
     * The other themes stack a coloured micro-bar, a logo lockup, a tagline and
     * a statistics strip before the page begins — around 180px of chrome above
     * the fold. Here the header is one row: who this is, how big the directory
     * is, and the two controls anyone actually uses. The statistics that used
     * to need their own band are three numerals on the right.
     *
     * $facultiesCount, $departmentsCount and $teachersCount arrive from the view
     * composer registered in AppServiceProvider for
     * frontend.themes.*.partials.header — which is why this file keeps its name
     * and its location.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
@endphp

<header class="sticky top-0 z-40"
        style="background: color-mix(in oklab, var(--page) 82%, transparent);
               backdrop-filter: blur(16px) saturate(150%);
               -webkit-backdrop-filter: blur(16px) saturate(150%);
               border-bottom: 1px solid var(--hairline-soft);">

    <div class="shell">
        <div class="flex items-center justify-between gap-4" style="height: var(--header-h);">

            {{-- The wordmark. The institution's name is the branding, so the
                 name sets it; the monogram only appears when there is no
                 uploaded logo to carry the mark instead. --}}
            <a href="{{ route('home') }}" wire:navigate
               class="flex items-center gap-3 min-w-0 shrink"
               aria-label="{{ $brand['site_name'] }} — {{ $brand['badge_text'] }}">

                @if(! empty($brand['use_image_logo']) && ! empty($brand['logo_url']))
                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['site_name'] }}"
                         class="h-9 w-auto shrink-0">
                @else
                    {{-- A small aurora of its own: the same gradient the rest of
                         the theme is built from, at 36px. --}}
                    <span class="shrink-0 grid place-items-center h-9 w-9 rounded-xl font-display text-sm font-extrabold text-white"
                          style="background: linear-gradient(135deg, var(--color-diu-primary), var(--color-diu-accent-light));
                                 box-shadow: 0 6px 18px -8px color-mix(in oklab, var(--color-diu-primary) 80%, transparent);">
                        {{ $brand['monogram'] }}
                    </span>
                @endif

                <span class="min-w-0">
                    <span class="flex items-baseline gap-2">
                        <span class="font-display text-[15px] font-extrabold tracking-tight truncate"
                              style="color: var(--ink);">
                            {{ $brand['site_short_name'] ?: $brand['short_name'] }}
                        </span>
                        @if(filled($brand['badge_text']))
                            <span class="hidden sm:inline eyebrow-quiet">{{ $brand['badge_text'] }}</span>
                        @endif
                    </span>
                    <span class="hidden sm:block truncate text-[11px] leading-tight mt-0.5"
                          style="color: var(--ink-4);">
                        {{ $brand['tagline'] ?: $brand['site_name'] }}
                    </span>
                </span>
            </a>

            {{-- The statistics band, reduced to what it was ever saying. --}}
            <p class="hidden xl:flex items-center gap-3 numeral shrink-0">
                <span>{{ number_format($facultiesCount ?? 0) }} <span style="color: var(--ink-4);">{{ $brand['stat_faculties_label'] }}</span></span>
                <span style="color: var(--hairline-strong);">·</span>
                <span>{{ number_format($departmentsCount ?? 0) }} <span style="color: var(--ink-4);">{{ $brand['stat_departments_label'] }}</span></span>
                <span style="color: var(--hairline-strong);">·</span>
                <span>{{ number_format($teachersCount ?? 0) }} <span style="color: var(--ink-4);">{{ $brand['stat_profiles_label'] }}</span></span>
            </p>

            <div class="flex items-center gap-1.5 shrink-0">
                {{-- The label needs room the phone does not have, so below md
                     the same link becomes an icon. It used to be `hidden
                     md:inline-flex` and nothing else — the only navigation link
                     in the header, gone under 768px, and the footer does not
                     carry it either, so on a phone the university's own site
                     was unreachable from the directory. --}}
                @if(! empty($brand['main_site_url']))
                    <a href="{{ $brand['main_site_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="nav-link hidden md:inline-flex px-3 py-1.5 text-[13px] font-semibold rounded-lg">
                        {{ $brand['main_site_label'] }}
                    </a>

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
                    <svg class="w-[18px] h-[18px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="w-[18px] h-[18px] hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>
                    </svg>
                </button>

                @auth
                    <a href="{{ url('/admin') }}" class="btn btn-primary">Dashboard</a>
                @else
                    <a href="{{ url('/admin/login') }}" class="btn btn-primary">{{ $brand['login_label'] }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>
