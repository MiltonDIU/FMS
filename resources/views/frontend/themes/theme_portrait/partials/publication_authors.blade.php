{{--
    The paper's authors, and which of them wrote it as one of ours.

    This theme's own copy, in this theme's own language: the palette comes from
    CSS variables and separation comes from hairlines rather than tinted pills,
    the way the facts list and the research block on this profile already read.
    Nothing outside theme_portrait/ is needed to render it, so removing another
    theme cannot take this page down.

    Every author is listed, in published order — a paper is not ours alone and a
    byline that hid the collaborators would be wrong. What is marked is the
    narrower thing: which of them carried this institution's affiliation on this
    paper. Per paper, not per person, because a teacher who joined last year has
    papers written under a previous employer and those are that employer's.

    Rows nothing has established are shown and said to be unestablished. Most of
    the table predates the column, and reading "we never looked" as "no" would
    drop real authors off the university's own count.

    Expects: $publication
--}}
@php
    $byline = $publication->byline();
@endphp

@if($byline->isNotEmpty())
    <section>
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="eyebrow">Authors</h3>
            <span class="count">{{ $byline->count() }}</span>
        </div>

        <ol>
            @foreach($byline as $author)
                <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-3 border-t py-3 text-[13px]"
                    style="border-color: var(--border-faint);">
                    <span class="tabular-nums" style="color: var(--text-soft);">{{ $loop->iteration }}</span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            @if($author['url'])
                                <a href="{{ $author['url'] }}" class="underline underline-offset-2"
                                   style="color: var(--brand-ink);">{{ $author['name'] }}</a>
                            @else
                                <span style="color: var(--text-base);">{{ $author['name'] }}</span>
                            @endif

                            @foreach($author['roles'] as $role)
                                @if($role !== 'co_author')
                                    <span class="text-[11px] uppercase tracking-wide" style="color: var(--text-soft);">
                                        {{ $role === 'first' ? 'first author' : 'corresponding' }}
                                    </span>
                                @endif
                            @endforeach

                            @if($author['is_ours'])
                                <span class="text-[11px] uppercase tracking-wide" style="color: var(--brand-ink);">
                                    {{ \App\Helpers\Institution::shortName() }}
                                </span>
                            @endif
                        </div>

                        @if(! $author['is_ours'])
                            @if($author['used_our_affiliation'] === null)
                                <p class="text-[12px] mt-0.5 italic" style="color: var(--text-soft);">affiliation not verified</p>
                            @elseif($author['affiliation'])
                                <p class="text-[12px] mt-0.5" style="color: var(--text-soft);">
                                    {{ \Illuminate\Support\Str::limit($author['affiliation'], 70) }}
                                </p>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
