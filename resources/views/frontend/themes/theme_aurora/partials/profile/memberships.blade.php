{{--
    Professional memberships.

    is_active decides whether a membership is still held, for the same reason
    is_current does under Experience: a missing end date means nobody recorded
    one, not that someone is still a member.
--}}
<section id="memberships" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Memberships</h2>
        <span class="numeral">{{ $teacher->memberships->count() }}</span>
    </div>

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

                // position and the membership type frequently hold the same
                // words ("Executive Member"), which printed the role twice.
                $facts = collect([
                    $mem->position,
                    $kind,
                    $mem->scope ? ucfirst($mem->scope) : null,
                    $mem->membership_id ? 'ID ' . $mem->membership_id : null,
                ])->filter('filled')->uniqueStrict(fn ($fact) => mb_strtolower(trim($fact)))->all();
            @endphp

            <div class="record">
                <span class="record-when">{{ $period }}</span>

                <span>
                    <span class="record-what block">{{ $org ?: ($kind ?: 'Membership') }}</span>

                    @if($facts)
                        <span class="record-sub block">{{ implode(' · ', $facts) }}</span>
                    @endif

                    @if($mem->url)
                        <a href="{{ $mem->url }}" target="_blank" rel="noopener noreferrer"
                           class="link-brand inline-block mt-1 text-[12px]">Society page &rarr;</a>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</section>
