@php
    /*
     * The department's Dean / Head / office contacts, rendered in the same main
     * stage the teacher cards normally occupy.
     *
     * It lives here rather than on a page of its own so the faculty and
     * department sidebar stays alongside it: from a department's contacts you
     * can step straight to the next department instead of going back first.
     *
     * Expects $contacts from App\Services\DepartmentContacts and $department.
     */
    use App\Services\DepartmentContacts;

    $sections = $contacts['sections'] ?? [];

    $groups = collect(DepartmentContacts::BLOCKS)
        ->map(fn ($block) => $block + ['people' => $sections[$block['key']] ?? []])
        ->filter(fn ($block) => ! empty($block['people']));
@endphp

@if($contacts['error'] ?? null)
    {{-- The service belongs to someone else; say so plainly and leave the rest
         of the page usable. --}}
    <p class="text-[14px] border-t pt-4" style="border-color: var(--border-soft); color: var(--text-muted);">
        {{ $contacts['error'] }}
    </p>
@elseif($groups->isEmpty())
    <p class="text-[15px] border-t pt-4" style="border-color: var(--border-soft); color: var(--text-muted);">
        No contact records are published for this department yet.
    </p>
@else
    <div class="space-y-10">
        @foreach($groups as $group)
            <section>
                <div class="flex items-baseline justify-between mb-2">
                    <h3 class="eyebrow">{{ $group['title'] }}</h3>
                    <span class="count">{{ count($group['people']) }}</span>
                </div>

                <div class="grid gap-x-10 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($group['people'] as $person)
                        <div class="flex gap-4 border-t py-5" style="border-color: var(--border-faint);">

                            {{-- A small square frame, not a circle: the same rule
                                 the teacher portraits follow, so nothing on this
                                 site gets cropped into a disc. --}}
                            <div class="portrait-frame rounded-md w-16 shrink-0">
                                @if(! empty($person['photo_url']))
                                    <img src="{{ $person['photo_url'] }}" alt="{{ $person['name'] }}">
                                @else
                                    <div class="portrait-fallback text-lg">
                                        {{ strtoupper(substr((string) $person['name'], 0, 1)) }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold leading-snug" style="color: var(--text-strong);">
                                    {{ $person['name'] }}
                                </p>

                                @if(filled($person['designation']))
                                    <p class="text-[13px] leading-snug" style="color: var(--text-soft);">
                                        {{ $person['designation'] }}
                                    </p>
                                @endif

                                @if(filled($person['email']))
                                    <a href="mailto:{{ $person['email'] }}"
                                       class="block mt-2 text-[12px] font-mono break-all hover:text-diu-primary transition-colors"
                                       style="color: var(--text-muted);">{{ $person['email'] }}</a>
                                @endif

                                @php
                                    $phones = array_filter([$person['mobile'] ?? null, $person['ip_phone'] ?? null], 'filled');
                                @endphp
                                @if($phones)
                                    <p class="text-[12px] mt-0.5" style="color: var(--text-muted);">
                                        {{ implode(' · ', $phones) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
