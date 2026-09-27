{{--
    A department's filter lists — the faculties, this faculty's departments, and
    (on the people view) designations and administrative roles.

    Rendered twice by department-search, for the same reason as
    filters_directory: the sidebar from the large breakpoint up, the search bar's
    drawer below it.

    On the contacts view only the navigation is offered. Designations and roles
    narrow a list of people, and there is no list of people on that view — but
    stepping to the next department is exactly as useful from its offices as
    from its staff.

    Expects $carry, $deptRoute and $idPrefix. $this is the DepartmentSearch
    component.
--}}
<div class="space-y-6">

    <!-- Academic Faculties -->
    <section class="rounded-xl" aria-labelledby="{{ $idPrefix }}-faculties">
        <h3 id="{{ $idPrefix }}-faculties" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
            Academic Faculties
        </h3>
        <ul class="border-l border-[#A7A9AC]" role="list">
            <li>
                <a href="{{ route('home', $carry) }}" wire:navigate
                   class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors border-transparent text-slate-700 hover:bg-[#EDF6FF]">
                    All Faculties
                </a>
            </li>
            @foreach($this->facultyList as $fac)
                @php
                    $active = ! $this->all && $this->department && $fac->id === $this->department->faculty_id;

                    // Built here rather than through $fac->url, which takes no
                    // query parameters. Same fallback as the accessor, since
                    // short_name is nullable and route() would throw.
                    $facUrl = $fac->short_name
                        ? route('faculty.show', array_merge(['faculty_short_name' => strtolower($fac->short_name)], $carry))
                        : route('home', $carry);
                @endphp
                <li>
                    <a href="{{ $facUrl }}" wire:navigate
                       class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                        <span class="truncate">{{ $fac->name }}</span>
                        <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm shrink-0">{{ $fac->code }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <!-- Departments (for selected faculty) -->
    @if($this->departmentList->isNotEmpty() && $this->department?->faculty?->short_name)
        <section class="rounded-xl" aria-labelledby="{{ $idPrefix }}-departments">
            <h3 id="{{ $idPrefix }}-departments" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Departments
            </h3>
            <ul class="border-l border-[#A7A9AC]" role="list">
                @foreach($this->departmentList as $dept)
                    @php $active = $this->department && $dept->id === $this->department->id; @endphp
                    <li>
                        <a href="{{ route('department.show', array_merge([
                                'faculty_short_name' => strtolower($this->department->faculty->short_name),
                                'department_code' => strtolower($dept->code),
                           ], $carry)) }}" wire:navigate
                           class="group flex w-full items-center justify-between gap-2 border-l-[3px] pl-5 pr-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                            <span class="truncate">{{ $dept->name }}</span>
                            <svg class="w-3.5 h-3.5 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($this->view !== 'contact')

        <!-- Designations -->
        @if($this->visibleDesignations->isNotEmpty())
            <section class="rounded-xl" aria-labelledby="{{ $idPrefix }}-designations">
                <h3 id="{{ $idPrefix }}-designations" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Academic Designations
                </h3>
                <ul class="border-l border-[#A7A9AC]" role="list">
                    <li>
                        <button type="button" wire:click="setDesignation(null)"
                            class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (! $this->designationId) ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                            All Designations
                        </button>
                    </li>
                    @foreach($this->visibleDesignations as $desig)
                        <li>
                            <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ ($this->designationId == $desig->id) ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                {{ $desig->name }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <!-- Administrative Roles -->
        @if($this->visibleAdminRoles->isNotEmpty())
            <section class="rounded-xl" aria-labelledby="{{ $idPrefix }}-roles">
                <h3 id="{{ $idPrefix }}-roles" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                    <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                    Administrative Roles
                </h3>
                <ul class="border-l border-[#A7A9AC]" role="list">
                    <li>
                        <button type="button" wire:click="setAdmin(null)"
                            class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (! $this->adminRoleId) ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                            All Roles
                        </button>
                    </li>
                    @foreach($this->visibleAdminRoles as $role)
                        <li>
                            <button type="button" wire:click="setAdmin({{ $role->id }})"
                                class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ ($this->adminRoleId == $role->id) ? 'bg-[#EDF6FF] border-diu-accent text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                                {{ $role->name }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(($this->designationId || $this->adminRoleId) && $deptRoute)
            <a href="{{ route('department.show', $deptRoute) }}" wire:navigate
               class="block w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
                Clear all filters
            </a>
        @endif
    @endif
</div>
