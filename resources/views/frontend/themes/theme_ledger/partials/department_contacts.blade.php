@php
    /*
     * The department's Dean, Head and office contacts, rendered in the same
     * place the ledger normally occupies.
     *
     * It lives inside the department component rather than on a page of its own
     * so the faculty and department index rails stay above it: from a
     * department's contacts you can step straight to the next department
     * instead of going back first.
     *
     * Set as ruled rows for the same reason the directory is — an office
     * contact is one line of facts, and four columns of them can be read down.
     * On a phone the mail address and the numbers fold under the name, since a
     * column of email addresses is unreadable at that width whatever is done to
     * it.
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
        <p class="title-md mb-1">No contacts published yet.</p>
        <p class="text-[14px]">This department has not registered office contacts.</p>
    </div>
@else
    <div class="space-y-9">
        @foreach($groups as $group)
            <section>
                <div class="flex items-baseline justify-between pb-2"
                     style="border-bottom: 1px solid var(--rule-strong);">
                    <h2 class="label label-ink">{{ $group['title'] }}</h2>
                    <span class="figure">{{ count($group['people']) }}</span>
                </div>

                @foreach($group['people'] as $person)
                    @php
                        $phones = array_filter([$person['mobile'] ?? null, $person['ip_phone'] ?? null], 'filled');
                    @endphp

                    <div class="flex items-start gap-4 py-3.5"
                         style="border-bottom: 1px solid var(--rule-soft);">

                        <span class="mugshot">
                            @if(! empty($person['photo_url']))
                                <img src="{{ $person['photo_url'] }}" alt="" loading="lazy">
                            @else
                                <span aria-hidden="true">{{ strtoupper(substr((string) $person['name'], 0, 1)) }}</span>
                            @endif
                        </span>

                        <div class="min-w-0 flex-1 grid gap-x-6 gap-y-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                            <div class="min-w-0">
                                <p class="text-[14px] font-semibold leading-snug" style="color: var(--ink);">
                                    {{ $person['name'] }}
                                </p>

                                @if(filled($person['designation']))
                                    <p class="text-[12.5px] leading-snug mt-0.5" style="color: var(--ink-3);">
                                        {{ $person['designation'] }}
                                    </p>
                                @endif
                            </div>

                            <div class="min-w-0">
                                @if(filled($person['email']))
                                    <a href="mailto:{{ $person['email'] }}"
                                       class="block font-mono text-[11.5px] link"
                                       style="overflow-wrap: anywhere;">{{ $person['email'] }}</a>
                                @endif

                                @if($phones)
                                    <p class="figure mt-1">{{ implode(' · ', $phones) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
@endif
