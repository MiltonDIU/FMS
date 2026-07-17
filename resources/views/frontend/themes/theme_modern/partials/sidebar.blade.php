@php
    $currentFaculty = $currentFaculty ?? null;
    $currentDepartment = $currentDepartment ?? null;
    $isHome = $isHome ?? false;
    $sticky = $sticky ?? true;

    $hasBase = (bool) ($currentFaculty->short_name ?? null);
    $baseRoute = $currentDepartment ? 'department.show' : 'faculty.show';
    $baseParams = ['faculty_short_name' => strtolower($currentFaculty->short_name ?? '')];
    if ($currentDepartment) {
        $baseParams['department_code'] = strtolower($currentDepartment->code);
    }
@endphp

<aside class="lg:col-span-1">
        <div class="{{ $sticky ? 'lg:sticky lg:top-28' : '' }} space-y-6">

        <!-- Academic Faculties -->
        <section class="rounded-2xl" aria-labelledby="sb-faculties">
            <h3 id="sb-faculties" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                Academic Faculties
            </h3>
            <ul class="border-l border-[#A7A9AC]" role="list">
                @foreach($faculties as $fac)
                    @php $active = $currentFaculty && strtolower($fac->short_name ?? '') === strtolower($currentFaculty->short_name ?? ''); @endphp
                    <li>
                        <a href="{{ $fac->url }}"
                           class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                            <span class="truncate">{{ $fac->name }}</span>
                            <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm shrink-0">{{ $fac->code }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <!-- Departments submenu when a faculty is active -->
        @if(!$isHome && $currentFaculty && $departments->isNotEmpty())
            <section class="rounded-2xl" aria-labelledby="sb-departments">
                <h3 id="sb-departments" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                    <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    Departments
                </h3>
                <ul class="border-l border-[#A7A9AC]" role="list">
                    @foreach($departments as $dept)
                        @php $dActive = $currentDepartment && strtolower($dept->code) === strtolower($currentDepartment->code); @endphp
                        <li>
                            <a href="{{ $hasBase ? route('department.show', ['faculty_short_name' => strtolower($currentFaculty->short_name), 'department_code' => strtolower($dept->code)]) : '#' }}"
                               class="group flex w-full items-center justify-between gap-2 border-l-[3px] pl-5 pr-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ $dActive ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                <span class="truncate">{{ $dept->name }}</span>
                                <svg class="w-3.5 h-3.5 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <!-- Designations & Administrative Roles -->
        @if(!$isHome && $currentFaculty && ($adminRoles->isNotEmpty() || $designations->isNotEmpty()))
            @if($adminRoles->isNotEmpty())
                <section class="rounded-2xl" aria-labelledby="sb-roles">
                    <h3 id="sb-roles" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                        Administrative Roles
                    </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        <li>
                            <a href="{{ $hasBase ? route($baseRoute, $baseParams) : '#' }}"
                               class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (!request('admin')) ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                All Roles
                            </a>
                        </li>
                        @foreach($adminRoles as $role)
                            <li>
                                <a href="{{ $hasBase ? route($baseRoute, $baseParams + ['admin' => $role->id]) : '#' }}"
                                   class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (request('admin') == $role->id) ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                    {{ $role->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($designations->isNotEmpty())
                <section class="rounded-2xl" aria-labelledby="sb-designations">
                    <h3 id="sb-designations" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Academic Designations
                    </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        <li>
                            <a href="{{ $hasBase ? route($baseRoute, $baseParams) : '#' }}"
                               class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (!request('designation')) ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                All Designations
                            </a>
                        </li>
                        @foreach($designations as $desig)
                            <li>
                                <a href="{{ $hasBase ? route($baseRoute, $baseParams + ['designation' => $desig->id]) : '#' }}"
                                   class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (request('designation') == $desig->id) ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                    {{ $desig->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif

    </div>
</aside>
