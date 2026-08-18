{{--
    The paper's authors, and which of them wrote it as one of ours.

    A full copy on purpose. This theme owns every file it renders, so deleting
    another theme cannot take this one down with it.

    Every author is listed, in published order — a paper is not ours alone and a
    byline that hid the collaborators would be wrong. What the page marks is the
    narrower thing: which of them carried this institution's affiliation on this
    paper. That is per paper, not per person, because a teacher who joined last
    year has papers written under a previous employer and those are that
    employer's output.

    Rows nothing has established are shown too, and said to be unestablished.
    Most of the table predates the column, and silently treating "we never
    looked" as "no" would quietly drop real authors off the university's count.

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
                        <span class="{{ $author['is_ours'] ? 'font-semibold text-slate-800' : 'text-slate-700' }}">{{ $author['name'] }}</span>
                    @endif

                    @foreach($author['roles'] as $role)
                        @if($role !== 'co_author')
                            <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-sm
                                {{ $role === 'first' ? 'bg-diu-primary/10 text-diu-primary' : 'bg-amber-100 text-amber-800' }}">
                                {{ $role === 'first' ? 'First author' : 'Corresponding' }}
                            </span>
                        @endif
                    @endforeach

                    @if($author['is_ours'])
                        <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-sm bg-emerald-100 text-emerald-800">
                            {{ \App\Helpers\Institution::shortName() }}
                        </span>
                    @elseif($author['used_our_affiliation'] === null)
                        <span class="text-[10px] text-slate-400 italic">affiliation not verified</span>
                    @elseif($author['affiliation'])
                        {{-- The institution rather than the whole address line, which
                             runs to a street in Dhaka and reads as noise on a byline. --}}
                        <span class="text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($author['affiliation'], 70) }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
