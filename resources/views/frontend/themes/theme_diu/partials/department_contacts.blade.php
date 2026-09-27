@php
    /*
     * The department's Dean, Head and office contacts, rendered in the stage the
     * faculty cards normally occupy.
     *
     * It lives inside the department component rather than on a page of its own
     * so the faculty and department navigation stays beside it: from a
     * department's contacts you can step straight to the next department
     * instead of going back first.
     *
     * Expects $contacts from App\Services\DepartmentContacts and $department.
     * photo_url comes from the service, which owns the backend's address — this
     * view used to hardcode it.
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
    <div class="p-4 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl">
        {{ $contacts['error'] }}
    </div>
@elseif($groups->isEmpty())
    <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
        <p class="text-gray-500 font-semibold">No contacts published yet.</p>
        <p class="text-xs text-slate-400 mt-1">This department has not registered office contacts.</p>
    </div>
@else
    <div class="space-y-8">
        @foreach($groups as $group)
            <section>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-1 h-6 bg-diu-primary rounded-full"></div>
                    <h3 class="font-display font-extrabold text-lg text-slate-900">{{ $group['title'] }}</h3>
                    <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ count($group['people']) }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($group['people'] as $person)
                        <div class="flex items-center gap-4 bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md hover:border-diu-primary/30 transition-all">
                            <div class="w-14 h-14 rounded-full overflow-hidden bg-slate-100 shrink-0 ring-1 ring-slate-200">
                                @if(! empty($person['photo_url']))
                                    <img src="{{ $person['photo_url'] }}" alt="{{ $person['name'] }}" loading="lazy" class="w-full h-full object-cover" />
                                @else
                                    <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-lg">
                                        {{ strtoupper(substr((string) $person['name'], 0, 1)) }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-900 leading-tight">{{ $person['name'] }}</p>
                                @if(filled($person['designation'] ?? null))
                                    <p class="text-[11px] font-semibold text-diu-primary mt-0.5">{{ $person['designation'] }}</p>
                                @endif

                                <div class="mt-1.5 space-y-0.5 text-[11px] text-slate-500">
                                    @if(filled($person['email'] ?? null))
                                        <a href="mailto:{{ $person['email'] }}" class="flex items-center gap-1.5 hover:text-diu-primary transition-colors min-w-0">
                                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                            <span class="font-mono" style="overflow-wrap: anywhere;">{{ $person['email'] }}</span>
                                        </a>
                                    @endif
                                    @foreach(array_filter([$person['mobile'] ?? null, $person['ip_phone'] ?? null], 'filled') as $phone)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            <span class="font-sans">{{ $phone }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
