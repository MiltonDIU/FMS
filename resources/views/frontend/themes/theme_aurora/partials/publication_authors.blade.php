{{--
    The paper's authors, in published order, each with their role on it.

    Every author is listed — a paper is not ours alone and a byline that hid the
    collaborators would be wrong. Each carries a role: first author,
    corresponding, or co-author for everybody else.

    The affiliation each author wrote under is not shown for now. Almost every
    authorship row predates the column that records it, so the page said
    "affiliation not verified" against nearly everybody, our own teachers
    included. Publication::byline() still carries it for when it is filled.

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

                            {{-- Co-author only when nothing more particular is true of them. --}}
                            @foreach(array_diff($author['roles'], ['co_author']) ?: ['co_author'] as $role)
                                <span class="text-[10.5px] uppercase tracking-wider" style="color: var(--ink-4);">
                                    {{ match ($role) { 'first' => 'first author', 'corresponding' => 'corresponding', default => 'co-author' } }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
