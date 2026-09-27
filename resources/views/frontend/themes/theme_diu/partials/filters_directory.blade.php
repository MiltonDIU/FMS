{{--
    The directory's filter lists — faculties, departments, designations and
    administrative roles.

    Rendered twice by teacher-search: once in the sidebar, which is how this
    theme has always looked from the large breakpoint up, and once inside the
    search bar's drawer below it. On a phone the sidebar used to stack above the
    results in full, so reaching the first face meant scrolling past every
    faculty, department and designation first; in the drawer it is one tap away
    and otherwise out of the way.

    Expects $carry (the query string every faculty and department link keeps) and
    $idPrefix, so the two copies do not share heading ids. $this is the
    TeacherSearch component — Livewire binds it for partials too.
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
                {{-- A link rather than wire:click selectFaculty(null). On /fbe that
                     cleared the list while leaving the address saying FBE, so a
                     reload or a shared link brought the faculty straight back. --}}
                <a href="{{ route('home', $carry) }}" wire:navigate
                   class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ ! $this->facultyId ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                    <span class="truncate">All Faculties</span>
                </a>
            </li>
            @foreach($this->faculties as $fac)
                @php
                    $active = (string) $fac->id === (string) $this->facultyId;

                    // Built here rather than through $fac->url, which takes no
                    // query parameters. Same fallback as the accessor:
                    // short_name is nullable, and route() would throw.
                    $facUrl = $fac->short_name
                        ? route('faculty.show', array_merge(['faculty_short_name' => strtolower($fac->short_name)], $carry))
                        : route('home', $carry);
                @endphp
                <li>
                    <a href="{{ $facUrl }}" wire:navigate
                       class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'bg-[#EDF6FF] border-diu-primary text-slate-900' : 'border-transparent text-slate-700 hover:bg-[#EDF6FF]' }}">
                        <span class="truncate">{{ $fac->name }}</span>
                        <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm shrink-0">{{ $fac->teachers_count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <!-- Departments (for selected faculty) -->
    @if($this->departments->isNotEmpty() && $this->selectedFaculty?->short_name)
        <section class="rounded-xl" aria-labelledby="{{ $idPrefix }}-departments">
            <h3 id="{{ $idPrefix }}-departments" class="flex items-center gap-2 text-lg font-bold text-[#58595B] border-b border-gray-100 pb-2 mb-3">
                <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Departments
            </h3>
            <ul class="border-l border-[#A7A9AC]" role="list">
                @foreach($this->departments as $dept)
                    @php $active = (string) $dept->id === (string) $this->departmentId; @endphp
                    <li>
                        <a href="{{ route('department.show', array_merge([
                                'faculty_short_name' => strtolower($this->selectedFaculty->short_name),
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

    <!-- Clear all -->
    @if($this->facultyId || $this->departmentId || $this->designationId || $this->adminRoleId)
        <a href="{{ route('home') }}" wire:navigate
            class="block w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
            Clear all filters
        </a>
    @endif
</div>
