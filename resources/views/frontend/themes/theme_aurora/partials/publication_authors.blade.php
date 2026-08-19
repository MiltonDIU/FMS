{{--
    The paper's authors, and which of them wrote it as one of ours.

    Every author is listed, in published order — a paper is not ours alone and a
    byline that hid the collaborators would be wrong. What is marked is the
    narrower thing: which of them carried this institution's affiliation on this
    paper. Per paper, not per person, because a teacher who joined last year has
    papers written under a previous employer, and those are that employer's.

    Rows nothing has established are shown, and said to be unestablished. Most
    of the table predates the column, and reading "we never looked" as "no"
    would drop real authors off the university's own count.

    Aurora's own copy, in Aurora's own language: hairlines and CSS-variable
    colour rather than tinted pills. Nothing outside theme_aurora/ is needed to
    render it.

    Expects: $publication
--}}
@php
    $byline = $publication->byline();
@endphp

@if($byline->isNotEmpty())
    <section>
        <div class="flex items-baseline justify-between mb-2">
            <h2 class="eyebrow">Authors</h2>
            <span class="numeral">{{ $byline->count() }}</span>
        </div>

        <ol>
            @foreach($byline as $author)
                <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-3 py-3 text-[13px]"
                    style="border-top: 1px solid var(--hairline-soft);">
                    <span class="numeral">{{ $loop->iteration }}</span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                            @if($author['url'])
                                <a href="{{ $author['url'] }}" class="link-brand">{{ $author['name'] }}</a>
                            @else
                                <span style="color: var(--ink-2);">{{ $author['name'] }}</span>
                            @endif

                            @foreach($author['roles'] as $role)
                                @if($role !== 'co_author')
                                    <span class="text-[10.5px] uppercase tracking-wider" style="color: var(--ink-4);">
                                        {{ $role === 'first' ? 'first author' : 'corresponding' }}
                                    </span>
                                @endif
                            @endforeach

                            @if($author['is_ours'])
                                <span class="text-[10.5px] uppercase tracking-wider font-bold" style="color: var(--brand-ink);">
                                    {{ \App\Helpers\Institution::shortName() }}
                                </span>
                            @endif
                        </div>

                        @if(! $author['is_ours'])
                            @if($author['used_our_affiliation'] === null)
                                <p class="text-[12px] mt-0.5 italic" style="color: var(--ink-4);">affiliation not verified</p>
                            @elseif($author['affiliation'])
                                <p class="text-[12px] mt-0.5" style="color: var(--ink-4);">
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
