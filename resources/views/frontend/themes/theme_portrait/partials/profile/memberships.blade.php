{{--
    Professional memberships.

    These carry dates too, so they take the same shape as everything else rather
    than the single-line bullet rows they were. is_active decides whether a
    membership is still held, for the same reason it does under Experience.
--}}
<div x-show="tab === 'memberships'" x-cloak>

    @if($teacher->memberships->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No professional bodies declared.
        </p>
    @else
        <div>
            @foreach($teacher->memberships as $mem)
                @php
                    $org = optional($mem->membershipOrganization)->name;
                    $kind = optional($mem->membershipType)->name;

                    $from = $mem->start_date ? \Illuminate\Support\Carbon::parse($mem->start_date)->format('Y') : null;
                    $to = $mem->end_date ? \Illuminate\Support\Carbon::parse($mem->end_date)->format('Y') : null;

                    $period = match (true) {
                        $mem->is_active && $from => $from . '–now',
                        (bool) $mem->is_active => 'Current',
                        (bool) ($from && $to) => $from === $to ? $from : $from . '–' . $to,
                        (bool) $from => $from,
                        (bool) $to => 'to ' . $to,
                        default => '—',
                    };

                    // position and the membership type often hold the same words
                    // ("Executive Member"), which printed the role twice.
                    $facts = collect([
                        $mem->position,
                        $kind,
                        $mem->scope ? ucfirst($mem->scope) : null,
                        $mem->membership_id ? 'ID ' . $mem->membership_id : null,
                    ])->filter('filled')->uniqueStrict(fn ($fact) => mb_strtolower(trim($fact)))->all();
                @endphp

                <div class="record-row">
                    <span class="record-meta">{{ $period }}</span>

                    <span>
                        <span class="record-title block">{{ $org ?: ($kind ?: 'Membership') }}</span>

                        @if($facts)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">
                                {{ implode(' · ', $facts) }}
                            </span>
                        @endif

                        @if($mem->url)
                            <a href="{{ $mem->url }}" target="_blank" rel="noopener noreferrer"
                               class="mt-1 inline-block text-[12px] hover:underline"
                               style="color: var(--brand-ink);">Society page &rarr;</a>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
