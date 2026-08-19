@php
    /*
     * The close of the page.
     *
     * The aurora runs the full height of the site, so the footer does not need
     * a slab of its own colour to feel like an ending — a hairline and a change
     * of scale is enough. The wordmark is set large because it is the last
     * thing on the page and there is nothing left for it to compete with.
     *
     * The per-theme footer_match_theme toggle still applies: an administrator
     * who turns it on gets the brand colour here, as in any other theme.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
    $matchFooter = filter_var(
        \App\Models\Setting::get(\App\Helpers\FontManager::settingKey('theme_aurora', 'footer_match_theme'), false),
        FILTER_VALIDATE_BOOLEAN,
    );
@endphp

<footer class="mt-24 {{ $matchFooter ? 'text-white' : '' }}"
        @if($matchFooter)
            style="background: linear-gradient(120deg, var(--color-diu-primary-dark), var(--color-diu-primary));"
        @else
            style="border-top: 1px solid var(--hairline-soft);"
        @endif>

    <div class="shell py-14">

        <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between">

            <div class="min-w-0">
                <p class="font-display text-2xl md:text-3xl font-extrabold tracking-tight leading-none
                          {{ $matchFooter ? '' : 'display-spectrum' }}">
                    {{ $brand['footer_name'] }}
                </p>
                <p class="mt-3 text-[11px] uppercase tracking-[0.18em]"
                   style="{{ $matchFooter ? 'color: rgba(255,255,255,0.7);' : 'color: var(--ink-4);' }}">
                    {{ $brand['footer_descriptor'] }}
                </p>

                @if(! empty($brand['address_footer']))
                    <p class="mt-5 max-w-sm text-[13px] leading-relaxed"
                       style="{{ $matchFooter ? 'color: rgba(255,255,255,0.82);' : 'color: var(--ink-3);' }}">
                        {{ $brand['address_footer'] }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col gap-6 sm:flex-row sm:gap-12">

                @if(! empty($brand['email']) || ! empty($brand['phone']))
                    <div>
                        <p class="{{ $matchFooter ? 'text-[11px] font-bold uppercase tracking-[0.18em] text-white/60' : 'eyebrow-quiet' }}">
                            Contact
                        </p>
                        <div class="mt-3 space-y-1.5 text-[13px]"
                             style="{{ $matchFooter ? 'color: rgba(255,255,255,0.88);' : 'color: var(--ink-2);' }}">
                            @if(! empty($brand['email']))
                                <p><a href="mailto:{{ $brand['email'] }}" class="hover:underline">{{ $brand['email'] }}</a></p>
                            @endif
                            @if(! empty($brand['phone']))
                                <p>{{ $brand['phone'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if(! empty($brand['social_links']))
                    <div>
                        <p class="{{ $matchFooter ? 'text-[11px] font-bold uppercase tracking-[0.18em] text-white/60' : 'eyebrow-quiet' }}">
                            Elsewhere
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-1">
                            @foreach($brand['social_links'] as $link)
                                @php $url = $link['url'] ?? ''; @endphp
                                @if(! empty($url))
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="btn-icon" title="{{ ucfirst($link['platform'] ?? '') }}">
                                        @include('frontend.themes.theme_aurora.partials.social_icon', ['platform' => $link['platform'] ?? 'website'])
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-12 pt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-[11px]"
             style="{{ $matchFooter
                ? 'border-top: 1px solid rgba(255,255,255,0.18); color: rgba(255,255,255,0.7);'
                : 'border-top: 1px solid var(--hairline-soft); color: var(--ink-4);' }}">
            <p>&copy; {{ date('Y') }} {{ $brand['footer_copyright'] }}. All rights reserved.</p>
            <p>{{ $brand['footer_accreditation'] }}</p>
        </div>
    </div>
</footer>
