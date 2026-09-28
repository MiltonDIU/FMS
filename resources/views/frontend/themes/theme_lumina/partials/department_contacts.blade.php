@php
    /*
     * The department's Dean, Head and office contacts, rendered in the same
     * stage the faculty tiles normally occupy.
     *
     * It lives inside the department component rather than on a page of its own
     * so the faculty and department chip rails stay above it: from a
     * department's contacts you can step straight to the next department
     * instead of going back first.
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
    {{-- The contacts service belongs to someone else. Say so plainly and leave
         the rest of the page usable. --}}
    <div class="empty">
        <p class="text-[14px]">{{ $contacts['error'] }}</p>
    </div>
@elseif($groups->isEmpty())
    <div class="empty">
        <p class="display-md mb-2">No contacts published yet.</p>
        <p class="text-[14px]">This department has not registered office contacts.</p>
    </div>
@else
    <div class="space-y-10">
        @foreach($groups as $group)
            <section>
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="eyebrow">{{ $group['title'] }}</h2>
                    <span class="numeral">{{ count($group['people']) }}</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($group['people'] as $person)
                        <div class="glass-soft rounded-xl p-4 flex gap-3.5">

                            <div class="mugshot">
                                @if(! empty($person['photo_url']))
                                    <img src="{{ $person['photo_url'] }}" alt="{{ $person['name'] }}" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ strtoupper(substr((string) $person['name'], 0, 1)) }}</span>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <p class="font-display text-[14px] font-bold leading-snug" style="color: var(--ink);">
                                    {{ $person['name'] }}
                                </p>

                                @if(filled($person['designation']))
                                    <p class="text-[12.5px] leading-snug mt-0.5" style="color: var(--ink-3);">
                                        {{ $person['designation'] }}
                                    </p>
                                @endif

                                @if(filled($person['email']))
                                    <a href="mailto:{{ $person['email'] }}"
                                       class="block mt-2 text-[11.5px] font-mono link-brand"
                                       style="overflow-wrap: anywhere;">{{ $person['email'] }}</a>
                                @endif

                                @php
                                    $phones = array_filter([$person['mobile'] ?? null, $person['ip_phone'] ?? null], 'filled');
                                @endphp
                                @if($phones)
                                    <p class="text-[11.5px] mt-1" style="color: var(--ink-4);">
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
