@php
    use App\Helpers\Branding;
    $brand = Branding::all();
    $matchFooter = filter_var(\App\Models\Setting::get('theme_theme_default_footer_match_theme', false), FILTER_VALIDATE_BOOLEAN);
@endphp
<footer class="{{ $matchFooter ? 'text-slate-200' : 'bg-slate-950 border-slate-800 text-slate-400' }} border-t text-xs mt-12 font-sans"
    {!! $matchFooter ? 'style="background-color: var(--color-diu-primary-dark); border-color: var(--color-diu-primary-light);"' : '' !!}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-b border-slate-800 pb-6 mb-6 pt-8">
            <div class="text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2">
                    <span class="font-display font-black text-sm text-white tracking-wide">{{ $brand['footer_name'] }}</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest font-semibold">{{ $brand['footer_descriptor'] }}</p>
            </div>

            <div class="flex flex-col items-center md:items-end gap-3">
                <span class="text-diu-accent">{{ $brand['address_footer'] }}</span>

                @if(! empty($brand['social_links']))
                    <div class="flex items-center gap-2">
                        @foreach($brand['social_links'] as $link)
                            @php $url = $link['url'] ?? ''; @endphp
                            @if(! empty($url))
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="w-7 h-7 rounded-full bg-slate-800 hover:bg-diu-primary text-slate-300 hover:text-white flex items-center justify-center transition-colors"
                                   title="{{ ucfirst($link['platform'] ?? '') }}">
                                    @include("frontend.themes.theme_default.partials.social_icon", ['platform' => $link['platform'] ?? 'website'])
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-[10px] text-slate-500 pb-8">
            <p>&copy; {{ date('Y') }} {{ $brand['footer_copyright'] }}. All rights reserved.</p>
            <p>{{ $brand['footer_accreditation'] }}</p>
        </div>
    </div>
</footer>
