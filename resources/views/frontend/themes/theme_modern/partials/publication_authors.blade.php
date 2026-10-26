{{--
    The paper's authors, in published order, each with their role on it.

    A full copy on purpose. This theme owns every file it renders, so deleting
    another theme cannot take this one down with it.

    Every author is listed — a paper is not ours alone and a byline that hid the
    collaborators would be wrong. Each carries a role: first author,
    corresponding, or co-author for everybody else.

    The affiliation each author wrote under is not shown for now. Almost every
    authorship row predates the column that records it, so the page said
    "affiliation not verified" against nearly everybody, our own teachers
    included. Publication::byline() still carries it for when it is filled.

    Expects: $publication
--}}
@php
    $byline = $publication->byline();
@endphp

@if($byline->isNotEmpty())
    <div class="space-y-2">
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Authors</p>

        <ol class="space-y-1.5">
            @foreach($byline as $author)
                <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm leading-snug">
                    <span class="text-slate-400 text-xs tabular-nums">{{ $loop->iteration }}.</span>

                    @if($author['url'])
                        <a href="{{ $author['url'] }}"
                           class="font-semibold text-diu-primary hover:underline">{{ $author['name'] }}</a>
                    @else
                        <span class="text-slate-700">{{ $author['name'] }}</span>
                    @endif

                    {{-- Co-author only when nothing more particular is true of them. --}}
                    @foreach(array_diff($author['roles'], ['co_author']) ?: ['co_author'] as $role)
                        <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-sm
                            {{ match ($role) {
                                'first' => 'bg-diu-primary/10 text-diu-primary',
                                'corresponding' => 'bg-amber-100 text-amber-800',
                                default => 'bg-slate-100 text-slate-600',
                            } }}">
                            {{ match ($role) {
                                'first' => 'First author',
                                'corresponding' => 'Corresponding',
                                default => 'Co-author',
                            } }}
                        </span>
                    @endforeach
                </li>
            @endforeach
        </ol>
    </div>
@endif
