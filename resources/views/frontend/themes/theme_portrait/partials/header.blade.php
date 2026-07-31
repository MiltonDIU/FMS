@php
    /*
     * A slim, mostly typographic masthead.
     *
     * The other themes open with a coloured micro-bar, a logo lockup, a tagline
     * and a statistics strip. Here the chrome gets out of the way so the
     * photographs below are the first saturated thing on the page: one hairline
     * rule, the wordmark, and the few links that are actually used.
     *
     * $facultiesCount, $departmentsCount and $teachersCount arrive from the view
     * composer registered in AppServiceProvider for
     * frontend.themes.*.partials.header — which is why this file keeps its name
     * and location.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
@endphp

<header class="sticky top-0 z-40 bg-surface-card border-b" style="border-color: var(--border-soft);">

    <div class="max-w-[88rem] mx-auto px-5 sm:px-8">
        <div class="flex items-center justify-between gap-6 py-4">

            {{-- Wordmark. The uploaded logo is used when there is one; otherwise
                 the monogram stands in, set in the display face rather than
                 dropped into a coloured circle. --}}
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 shrink-0">
                @if(! empty($brand['use_image_logo']) && ! empty($brand['logo_url']))
                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['site_name'] }}" class="h-9 w-auto">
                @else
                    <span class="font-display text-xl font-extrabold tracking-tight"
                          style="color: var(--color-diu-primary);">
                        {{ $brand['monogram'] ?: $brand['site_short_name'] }}
                    </span>
                @endif

                <span class="hidden sm:block leading-tight">
                    <span class="block font-display text-[13px] font-bold tracking-tight"
                          style="color: var(--text-strong);">{{ $brand['portal_label'] }}</span>
                    <span class="block text-[11px]" style="color: var(--text-muted);">{{ $brand['portal_sublabel'] }}</span>
                </span>
            </a>

            {{-- Counts as running text rather than a panel of tiles: context,
                 not a headline. --}}
            <p class="hidden lg:block count">
                {{ number_format($facultiesCount ?? 0) }} {{ $brand['stat_faculties_label'] }}
                <span class="mx-2" style="color: var(--border-strong);">/</span>
                {{ number_format($departmentsCount ?? 0) }} {{ $brand['stat_departments_label'] }}
                <span class="mx-2" style="color: var(--border-strong);">/</span>
                {{ number_format($teachersCount ?? 0) }} {{ $brand['stat_profiles_label'] }}
            </p>

            <div class="flex items-center gap-1">
                @if(! empty($brand['main_site_url']))
                    <a href="{{ $brand['main_site_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="hidden md:inline-flex items-center px-3 py-1.5 text-[13px] font-medium rounded-md hover:bg-surface-hover transition-colors"
                       style="color: var(--text-soft);">
                        {{ $brand['main_site_label'] }}
                    </a>
                @endif

                <button type="button" id="appearance-toggle" aria-label="Toggle dark mode"
                        class="p-2 rounded-md hover:bg-surface-hover transition-colors"
                        style="color: var(--text-soft);">
                    <svg class="w-4 h-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="w-4 h-4 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>
                    </svg>
                </button>

                @auth
                    <a href="{{ url('/admin') }}"
                       class="inline-flex items-center px-3 py-1.5 text-[13px] font-semibold rounded-md text-white"
                       style="background-color: var(--color-diu-primary);">Dashboard</a>
                @else
                    <a href="{{ url('/admin/login') }}"
                       class="inline-flex items-center px-3 py-1.5 text-[13px] font-semibold rounded-md text-white"
                       style="background-color: var(--color-diu-primary);">{{ $brand['login_label'] }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>
