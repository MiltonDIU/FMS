@php
    /*
     * The colophon.
     *
     * Set as three ruled columns rather than a coloured slab, so the end of the
     * page is a change of scale rather than a change of material — the same
     * reasoning the rest of the theme uses for not drawing boxes.
     *
     * The per-theme footer_match_theme toggle still applies: an administrator
     * who turns it on gets the brand colour here, as in every other theme. The
     * text tokens are overridden inline for that case rather than by a second
     * palette, because it is one block and a whole alternate scheme for it would
     * be more machinery than the switch is worth.
     */
    use App\Helpers\Branding;

    $brand = Branding::all();
    $matchFooter = filter_var(
        \App\Models\Setting::get(\App\Helpers\FontManager::settingKey('theme_ledger', 'footer_match_theme'), false),
        FILTER_VALIDATE_BOOLEAN,
    );

    $quiet = $matchFooter ? 'color: rgba(255,255,255,0.66);' : 'color: var(--ink-4);';
    $body = $matchFooter ? 'color: rgba(255,255,255,0.88);' : 'color: var(--ink-2);';
    $ruleColour = $matchFooter ? 'rgba(255,255,255,0.22)' : 'var(--rule)';
@endphp

<footer class="mt-20 {{ $matchFooter ? 'text-white' : '' }}"
        @if($matchFooter)
            style="background: var(--color-diu-primary-dark); border-top: 1px solid {{ $ruleColour }};"
        @else
            style="border-top: 3px double var(--rule-strong);"
        @endif>

    <div class="sheet py-12">

        <div class="grid gap-9 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)] md:gap-12">

            <div class="min-w-0">
                <p class="title-md" style="{{ $matchFooter ? 'color: #fff;' : '' }}">{{ $brand['footer_name'] }}</p>
                <p class="label mt-2" style="{{ $quiet }}">{{ $brand['footer_descriptor'] }}</p>

                @if(! empty($brand['address_footer']))
                    <p class="mt-4 max-w-sm text-[13px] leading-relaxed" style="{{ $body }}">
                        {{ $brand['address_footer'] }}
                    </p>
                @endif
            </div>

            @if(! empty($brand['email']) || ! empty($brand['phone']))
                <div class="min-w-0">
                    <p class="label pb-2" style="{{ $quiet }} border-bottom: 1px solid {{ $ruleColour }};">Contact</p>
                    <div class="mt-3 space-y-1.5 text-[13px]" style="{{ $body }}">
                        @if(! empty($brand['email']))
                            <p><a href="mailto:{{ $brand['email'] }}" class="hover:underline"
                                  style="overflow-wrap: anywhere;">{{ $brand['email'] }}</a></p>
                        @endif
                        @if(! empty($brand['phone']))
                            <p>{{ $brand['phone'] }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if(! empty($brand['social_links']))
                <div class="min-w-0">
                    <p class="label pb-2" style="{{ $quiet }} border-bottom: 1px solid {{ $ruleColour }};">Elsewhere</p>
                    <div class="mt-3 flex flex-wrap items-center gap-1">
                        @foreach($brand['social_links'] as $link)
                            @php $url = $link['url'] ?? ''; @endphp
                            @if(! empty($url))
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="btn-icon" title="{{ ucfirst($link['platform'] ?? '') }}">
                                    @include('frontend.themes.theme_ledger.partials.social_icon', ['platform' => $link['platform'] ?? 'website'])
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-10 pt-5 flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between figure"
             style="border-top: 1px solid {{ $ruleColour }}; {{ $quiet }}">
            <p>&copy; {{ date('Y') }} {{ $brand['footer_copyright'] }}. All rights reserved.</p>
            <p>{{ $brand['footer_accreditation'] }}</p>
        </div>
    </div>
</footer>
