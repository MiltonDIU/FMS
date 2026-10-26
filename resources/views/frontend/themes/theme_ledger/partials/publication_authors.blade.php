{{--
    The paper's authors, in published order, each with their role on it.

    Every author is listed — a paper is not ours alone and a byline that hid the
    collaborators would be wrong. Each carries a role: first author,
    corresponding, or co-author for everybody else.

    The affiliation each author wrote under is not shown for now. Almost every
    authorship row predates the column that records it, so the page said
    "affiliation not verified" against nearly everybody, our own teachers
    included. Publication::byline() still carries it for when it is filled.

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

                            {{-- Co-author only when nothing more particular is true of them. --}}
                            @foreach(array_diff($author['roles'], ['co_author']) ?: ['co_author'] as $role)
                                <span class="stamp" style="margin-left: 0;">
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
