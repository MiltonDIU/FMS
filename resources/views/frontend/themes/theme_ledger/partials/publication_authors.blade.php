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

    Ledger's own copy, in Ledger's own language: a numbered roll, the position
    in the gutter the way a year is everywhere else on the site, and roles set
    as stamps rather than tinted pills. Nothing outside theme_ledger/ is needed
    to render it.

    Expects: $publication
--}}
@php
    $byline = $publication->byline();
@endphp

@if($byline->isNotEmpty())
    <section>
        <div class="doc-head">
            <h2 class="title-md">Authors</h2>
            <span class="figure">{{ $byline->count() }}</span>
        </div>

        <ol class="records">
            @foreach($byline as $author)
                <li class="record">
                    <span class="record-when">{{ $loop->iteration }}</span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            @if($author['url'])
                                <a href="{{ $author['url'] }}" class="record-what link">{{ $author['name'] }}</a>
                            @else
                                <span class="record-what">{{ $author['name'] }}</span>
                            @endif

                            @foreach($author['roles'] as $role)
                                @if($role !== 'co_author')
                                    <span class="stamp" style="margin-left: 0;">
                                        {{ $role === 'first' ? 'first author' : 'corresponding' }}
                                    </span>
                                @endif
                            @endforeach

                            @if($author['is_ours'])
                                <span class="stamp" style="margin-left: 0; color: var(--brand-ink); border-color: var(--brand-ink);">
                                    {{ \App\Helpers\Institution::shortName() }}
                                </span>
                            @endif
                        </div>

                        @if(! $author['is_ours'])
                            @if($author['used_our_affiliation'] === null)
                                <span class="record-note block italic">affiliation not verified</span>
                            @elseif($author['affiliation'])
                                <span class="record-note block">
                                    {{ \Illuminate\Support\Str::limit($author['affiliation'], 70) }}
                                </span>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
