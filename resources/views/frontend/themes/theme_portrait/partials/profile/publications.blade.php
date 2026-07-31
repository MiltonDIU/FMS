{{--
    Publications.

    The longest list on the page — some profiles run to hundreds — so it is the
    one that gained most from losing the cards: year in its own column, title
    doing the talking, venue underneath. The whole row is the link, so there is
    no separate "View details" control to aim at.
--}}
<div x-show="tab === 'publications'" x-cloak>

    @if($teacher->publications->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No publications have been added yet.
        </p>
    @else
        <div>
            @foreach($teacher->publications as $pub)
                @php
                    $pubUrl = ($faculty->short_name && $teacher->webpage)
                        ? route('publication.show', [
                            'faculty_short_name' => strtolower($faculty->short_name),
                            'department_code' => strtolower($department->code),
                            'teacher_webpage' => $teacher->webpage,
                            'publication_slug' => $pub->slug ?: \Illuminate\Support\Str::slug($pub->title),
                        ])
                        : null;
                    $type = optional($pub->type)->name;
                @endphp

                <a @if($pubUrl) href="{{ $pubUrl }}" wire:navigate @endif
                   class="record-row group @if(! $pubUrl) pointer-events-none @endif">
                    <span class="record-meta">{{ $pub->publication_year ?: '—' }}</span>

                    <span>
                        <span class="record-title block transition-colors group-hover:text-diu-primary">
                            {{ $pub->title }}
                        </span>

                        @if($pub->journal_name)
                            <span class="mt-1 block text-[13px] italic" style="color: var(--text-soft);">
                                {{ $pub->journal_name }}
                            </span>
                        @endif

                        @if($type)
                            <span class="mt-1 block text-[11px] uppercase tracking-wider"
                                  style="color: var(--text-muted);">{{ $type }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
