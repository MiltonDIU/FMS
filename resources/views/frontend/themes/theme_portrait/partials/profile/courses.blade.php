{{--
    Teaching areas.

    A plain list. Each entry is two or three words, so a grid of bordered tiles
    with an icon in every one was more furniture than content.
--}}
<div x-show="tab === 'courses'" x-cloak>

    @if($teacher->teachingAreas->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No teaching areas recorded.
        </p>
    @else
        <div class="grid gap-x-12 sm:grid-cols-2">
            @foreach($teacher->teachingAreas as $area)
                <div class="border-t py-3.5" style="border-color: var(--border-faint);">
                    <span class="text-[15px]" style="color: var(--text-strong);">{{ $area->area }}</span>
                    @if($area->description)
                        <span class="mt-1 block text-[13px] leading-relaxed" style="color: var(--text-muted);">
                            {{ $area->description }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
