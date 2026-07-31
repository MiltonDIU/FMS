@php
    /*
     * A quiet close. The other themes end on a near-black slab; this one keeps
     * the same white ground as the rest of the page and separates itself with a
     * single rule, so nothing competes with the photographs above it.
     *
     * The footer_match_theme setting still applies — an administrator who turns
     * it on gets the brand colour here as they would in any other theme.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
    $matchFooter = filter_var(
        \App\Models\Setting::get('theme_theme_portrait_footer_match_theme', false),
        FILTER_VALIDATE_BOOLEAN,
    );
@endphp

<footer class="mt-20 border-t {{ $matchFooter ? 'text-white/80' : 'bg-surface-card' }}"
        @if($matchFooter)
            style="background-color: var(--color-diu-primary-dark); border-color: var(--color-diu-primary);"
        @else
            style="border-color: var(--border-soft);"
        @endif>

    <div class="max-w-[88rem] mx-auto px-5 sm:px-8 py-12">

        <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

            <div>
                <p class="font-display text-sm font-extrabold tracking-tight"
                   @if(! $matchFooter) style="color: var(--text-strong);" @endif>
                    {{ $brand['footer_name'] }}
                </p>
                <p class="mt-1 text-[11px] uppercase tracking-[0.16em]"
                   @if(! $matchFooter) style="color: var(--text-muted);" @endif>
                    {{ $brand['footer_descriptor'] }}
                </p>
                @if(! empty($brand['address_footer']))
                    <p class="mt-4 text-[13px] max-w-sm leading-relaxed"
                       @if(! $matchFooter) style="color: var(--text-soft);" @endif>
                        {{ $brand['address_footer'] }}
                    </p>
                @endif
            </div>

            @if(! empty($brand['social_links']))
                <div class="flex items-center gap-1">
                    @foreach($brand['social_links'] as $link)
                        @php $url = $link['url'] ?? ''; @endphp
                        @if(! empty($url))
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                               class="w-8 h-8 rounded-md flex items-center justify-center transition-colors hover:bg-surface-hover"
                               @if(! $matchFooter) style="color: var(--text-muted);" @endif
                               title="{{ ucfirst($link['platform'] ?? '') }}">
                                @include('frontend.themes.theme_portrait.partials.social_icon', ['platform' => $link['platform'] ?? 'website'])
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-10 pt-6 border-t flex flex-col sm:flex-row justify-between gap-2 text-[11px]"
             @if($matchFooter)
                 style="border-color: color-mix(in srgb, white 18%, transparent);"
             @else
                 style="border-color: var(--border-faint); color: var(--text-muted);"
             @endif>
            <p>&copy; {{ date('Y') }} {{ $brand['footer_copyright'] }}. All rights reserved.</p>
            <p>{{ $brand['footer_accreditation'] }}</p>
        </div>
    </div>
</footer>
