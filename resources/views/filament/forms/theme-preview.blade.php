@php
    /*
     * Shows what the theme selected above actually is: its screenshot if it
     * ships one, what it calls itself, and a link that renders the live site
     * under it without switching anyone else over.
     *
     * Screenshots are optional by design. A theme is a folder someone can drop
     * in, and requiring an image would mean a working theme could not be
     * installed; when there is none this says so plainly rather than showing a
     * fabricated preview of a layout nobody has seen.
     */
    use App\Helpers\Theme;

    // $slug arrives from viewData() reading the Active Theme dropdown, so this
    // card follows the selection without holding state of its own.
    $meta = $slug ? Theme::meta($slug) : null;
    $previewUrl = $slug ? route('home', [Theme::PREVIEW_PARAM => $slug]) : null;
@endphp

<div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
    @if(! $meta)
        <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
            Select a theme to see its details.
        </p>
    @else
        <div class="aspect-16/9 bg-gray-50 dark:bg-white/5 flex items-center justify-center">
            @if($meta['screenshot'])
                <img src="{{ route('theme.screenshot', ['theme' => $slug]) }}"
                     alt="{{ $meta['name'] }} theme"
                     class="w-full h-full object-cover object-top">
            @else
                <div class="text-center px-6 py-10">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No screenshot yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Drop a <code class="font-mono">screenshot.png</code> into
                        <code class="font-mono">resources/views/frontend/themes/{{ $slug }}/</code>
                        and it appears here. Until then, use the live preview below.
                    </p>
                </div>
            @endif
        </div>

        <div class="p-4 border-t border-gray-200 dark:border-white/10">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $meta['name'] }}</span>
                <span class="font-mono text-xs text-gray-400">{{ $slug }}</span>
                @if($meta['version'])
                    <span class="text-xs text-gray-400">v{{ $meta['version'] }}</span>
                @endif
            </div>

            @if($meta['description'])
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    {{ $meta['description'] }}
                </p>
            @endif

            <div class="mt-3 flex flex-wrap items-center gap-4 text-xs">
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
                   class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                    Open live preview &rarr;
                </a>
                @if($meta['author'])
                    <span class="text-gray-400">{{ $meta['author'] }}</span>
                @endif
            </div>

            <p class="mt-2 text-xs text-gray-400">
                The preview shows the real site under this theme and is visible only to you.
                Nothing changes for visitors until you save.
            </p>
        </div>
    @endif
</div>
