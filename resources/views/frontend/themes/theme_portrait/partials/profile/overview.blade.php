{{--
    Overview.

    The tab strip already names this panel, so nothing repeats the word
    "Overview" here. Biography leads because it is the only prose on the page;
    the two lists beside it are short and factual, so they are lists rather than
    the bordered cards this theme had inherited.
--}}
<div x-show="tab === 'overview'" x-cloak>

    @php
        $bio = $teacher->bio ?: implode(', ', $teacher->researchInterestNames());
        $interests = $teacher->researchInterestNames();
    @endphp

    @if(filled($bio))
        <p class="text-[15px] leading-[1.75] max-w-[68ch]" style="color: var(--text-base);">
            {{ $bio }}
        </p>
    @else
        <p class="text-[15px]" style="color: var(--text-muted);">
            No biography has been added yet.
        </p>
    @endif

    <div class="mt-12 grid gap-x-12 gap-y-10 sm:grid-cols-2">

        <section>
            <div class="flex items-baseline justify-between mb-2">
                <h3 class="eyebrow">Teaching areas</h3>
                @if($teacher->teachingAreas->isNotEmpty())
                    <span class="count">{{ $teacher->teachingAreas->count() }}</span>
                @endif
            </div>

            @if($teacher->teachingAreas->isEmpty())
                <p class="border-t pt-3 text-[14px]"
                   style="border-color: var(--border-faint); color: var(--text-muted);">
                    None specified.
                </p>
            @else
                <ul>
                    @foreach($teacher->teachingAreas as $area)
                        <li class="border-t py-2.5 text-[14px]"
                            style="border-color: var(--border-faint); color: var(--text-soft);">
                            {{ $area->area }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <div class="flex items-baseline justify-between mb-2">
                <h3 class="eyebrow">Research interests</h3>
                @if(count($interests))
                    <span class="count">{{ count($interests) }}</span>
                @endif
            </div>

            @if(empty($interests))
                <p class="border-t pt-3 text-[14px]"
                   style="border-color: var(--border-faint); color: var(--text-muted);">
                    None listed.
                </p>
            @else
                <ul>
                    @foreach($interests as $interest)
                        <li class="border-t py-2.5 text-[14px]"
                            style="border-color: var(--border-faint); color: var(--text-soft);">
                            {{ $interest }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
