{{--
    Who Scopus said corresponded, and room to disagree.

    The correspondence address names somebody on 90% of rows, and on 71% of them
    it is not the first-listed author — which is the whole reason this is worth
    showing. The other 10% is why the box is here: nothing named anybody, and a
    reviewer who knows can say so.

    Names rather than positions, matched back the same way the address is, so
    nobody has to count along the author list to check the answer.
--}}
@php
    $detected = app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)->correspondingNames($paper);
    $override = $paper['corresponding_override'] ?? null;
    $settled = ($paper['decision'] ?? '') === 'imported';
@endphp

<td class="align-top">
    <div class="max-w-[200px] space-y-1">
        @if($detected !== '')
            <div class="text-[11px] leading-snug {{ $override ? 'text-primary-700 dark:text-primary-300 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                {{ $detected }}
            </div>
            @if($override)
                <span class="text-[9px] uppercase tracking-wide text-primary-600 dark:text-primary-400">Your answer</span>
            @endif
        @else
            <span class="bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-300 px-1.5 py-0.5 rounded text-[10px] font-semibold">
                Not named
            </span>
        @endif

        @unless($settled)
            <input type="text"
                value="{{ $override }}"
                placeholder="Override — type name(s)"
                data-paper-key="{{ $pKey }}"
                @change="$wire.updateCorrespondingOverride(importId, $event.target.dataset.paperKey, $event.target.value)"
                class="w-full text-[10px] bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded px-1.5 py-0.5 focus:ring-primary-500">
        @endunless
    </div>
</td>
