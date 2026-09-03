<!-- Memberships Tab -->
<div x-show="tab === 'memberships'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-5-4-4-4-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Professional Memberships &amp; Affiliations
    </h3>
    @if($teacher->memberships->isEmpty())
        <p class="text-xs text-slate-400 font-sans">No affiliated professional bodies declared.</p>
    @else
        <div class="space-y-2">
            {{-- Newest first, with memberships still held at the top.

                 The same correction as Experience: every memberships row carries
                 sort_order 0, so the relation's ordering did nothing and the list came
                 out in insertion order. is_active decides what still counts as held, for
                 the same reason it does below — a missing end date means nobody recorded
                 one, not that the membership lapsed. --}}
            @php
                $membershipList = $teacher->memberships->sortByDesc(
                    fn ($mem) => ($mem->is_active ? '1' : '0') . ($mem->start_date?->format('Ymd') ?? '00000000'),
                );
            @endphp

            @foreach($membershipList as $mem)
                <div class="p-3 rounded-2xl border border-slate-200 flex items-center gap-2.5 text-xs text-slate-700 font-sans font-medium ring-1 ring-slate-900/5">
                    <div class="w-2 h-2 rounded-full bg-diu-primary shrink-0"></div>
                    <div class="min-w-0">
                        <span class="font-semibold text-slate-800">{{ optional($mem->membershipType)->name ?? 'Membership' }}</span>
                        @if($mem->membershipOrganization?->name)
                            <span class="text-slate-500">&middot; {{ $mem->membershipOrganization->name }}</span>
                        @endif
                        @if($mem->position)
                            <span class="text-slate-500">&middot; {{ $mem->position }}</span>
                        @endif
                        @if($mem->scope)
                            <span class="text-slate-500">&middot; {{ ucfirst($mem->scope) }}</span>
                        @endif
                        @if($mem->membership_id)
                            <span class="text-slate-500">&middot; ID {{ $mem->membership_id }}</span>
                        @endif
                        @php
                            $from = $mem->start_date?->format('Y');
                            $to = $mem->end_date?->format('Y');

                            // The period used to print "Present" whenever there
                            // was no end date, which told a visitor that every
                            // lapsed membership was still held. is_active is the
                            // column that actually answers that.
                            $period = match (true) {
                                (bool) $mem->is_active => $from ? $from . '–now' : 'Current',
                                (bool) ($from && $to) => $from === $to ? $from : $from . '–' . $to,
                                (bool) $from => $from,
                                (bool) $to => 'to ' . $to,
                                default => null,
                            };
                        @endphp
                        @if($period)
                            <span class="text-slate-400 text-[10px]">&middot; {{ $period }}</span>
                        @endif
                        @if($mem->url)
                            <a href="{{ $mem->url }}" target="_blank" rel="noopener noreferrer"
                               class="text-diu-primary font-semibold ml-1 hover:underline">Society page &rarr;</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
