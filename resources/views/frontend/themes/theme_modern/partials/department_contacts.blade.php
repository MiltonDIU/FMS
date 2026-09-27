@php
    /*
     * The department's office details, then its Dean, Head and office contacts,
     * rendered in the stage the faculty cards normally occupy.
     *
     * It lives inside the department component rather than on a page of its own
     * so the faculty and department navigation stays beside it: from a
     * department's contacts you can step straight to the next department
     * instead of going back first.
     *
     * Expects $contacts from App\Services\DepartmentContacts and $department.
     * photo_url comes from the service, which owns the backend's address — this
     * theme used to hardcode it.
     */
    use App\Helpers\Branding;
    use App\Services\DepartmentContacts;

    $sections = $contacts['sections'] ?? [];
    $info = $sections['department'] ?? [];

    $groups = collect(DepartmentContacts::BLOCKS)
        ->map(fn ($block) => $block + ['people' => $sections[$block['key']] ?? []])
        ->filter(fn ($block) => ! empty($block['people']));

    $deptAddress = $info['address'] ?? ($info['location'] ?? Branding::get('address_full'));
    $deptEmail = $info['email'] ?? null;
    $deptPhone = $info['phone'] ?? ($info['mobile'] ?? null);

    // Institutional contact from the "Contact & External Links" settings —
    // common to every page, and there even when the department API is not.
    $orgEmail = Branding::get('email');
    $orgPhone = Branding::get('phone');

    $totalContacts = $groups->sum(fn ($g) => count($g['people']));
@endphp

<div class="space-y-5">

    {{-- How to reach the office itself. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <div class="glass-panel rounded-3xl p-5 flex flex-col justify-center gap-4">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-1 h-5 bg-diu-primary rounded-full"></div>
                <p class="text-[10px] uppercase font-bold tracking-widest text-slate-500">Reach the Office</p>
            </div>

            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-diu-primary/10 border border-diu-primary/20 flex items-center justify-center shrink-0 text-diu-primary">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Email</p>
                    @if($orgEmail || $deptEmail)
                        <a href="mailto:{{ $orgEmail ?: $deptEmail }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors truncate block font-mono">{{ $orgEmail ?: $deptEmail }}</a>
                    @else
                        <p class="text-xs font-semibold text-slate-400">Not available</p>
                    @endif
                    @if($deptEmail && $orgEmail && $deptEmail !== $orgEmail)
                        <a href="mailto:{{ $deptEmail }}" class="text-[10px] text-slate-400 hover:text-diu-primary transition-colors truncate block mt-0.5">{{ $deptEmail }}</a>
                    @endif
                </div>
            </div>

            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-diu-primary/10 border border-diu-primary/20 flex items-center justify-center shrink-0 text-diu-primary">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Phone</p>
                    @if($orgPhone || $deptPhone)
                        <a href="tel:{{ $orgPhone ?: $deptPhone }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors">{{ $orgPhone ?: $deptPhone }}</a>
                    @else
                        <p class="text-xs font-semibold text-slate-400">Not available</p>
                    @endif
                    @if($deptPhone && $orgPhone && $deptPhone !== $orgPhone)
                        <a href="tel:{{ $deptPhone }}" class="text-[10px] text-slate-400 hover:text-diu-primary transition-colors block mt-0.5">{{ $deptPhone }}</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="glass-panel rounded-3xl p-5 flex flex-col justify-center gap-4">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-diu-accent/10 border border-diu-accent/20 flex items-center justify-center shrink-0 text-diu-accent">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Location</p>
                    <p class="text-xs font-semibold text-slate-700 leading-snug">{{ $deptAddress }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-600">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Directory</p>
                    <p class="text-xs font-semibold text-slate-700">
                        <span class="font-display font-extrabold text-slate-900">{{ $totalContacts }}</span> {{ \Illuminate\Support\Str::plural('contact', $totalContacts) }} listed
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="p-6 md:p-8">
            @if($contacts['error'] ?? null)
                {{-- The contacts service belongs to someone else. Say so plainly
                     and leave the rest of the page usable. --}}
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-2xl">
                    {{ $contacts['error'] }}
                </div>
            @elseif($groups->isEmpty())
                <div class="p-12 text-center text-slate-400 text-sm">No contact records found for this department.</div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    @foreach($groups as $group)
                        <section>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-1 h-6 bg-diu-primary rounded-full"></div>
                                <h3 class="font-display font-extrabold text-lg text-slate-900">{{ $group['title'] }}</h3>
                                <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ count($group['people']) }}</span>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                @foreach($group['people'] as $person)
                                    <div class="flex items-center gap-4 card card-hover rounded-2xl p-4">
                                        <div class="w-16 h-16 rounded-2xl overflow-hidden bg-slate-100 shrink-0 ring-1 ring-slate-200">
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
        </div>
    </div>
</div>
