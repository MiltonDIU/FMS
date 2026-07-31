<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-1">
        <div class="{{ true ? 'lg:sticky lg:top-28' : '' }} space-y-6">
            <!-- Academic Faculties -->
            <section class="rounded-xl" aria-labelledby="sb-faculties">
                <h3 id="sb-faculties" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    Academic Faculties
                </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        <li>
                            <button type="button" wire:click="toggleAll"
                                class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ $this->all ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                All Faculties
                            </button>
                        </li>
                        @php
                            $faculties = \App\Models\Faculty::where('is_active', true)->orderBy('sort_order')->get();
                        @endphp
                        @foreach($faculties as $fac)
                            @php $active = ! $this->all && $this->department && $fac->id === $this->department->faculty_id; @endphp
                            <li>
                                <a href="{{ $fac->url }}" wire:navigate
                                   class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                    <span class="truncate">{{ $fac->name }}</span>
                                    <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm shrink-0">{{ $fac->code }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
            </section>

            <!-- Departments (for selected faculty) -->
            @if($this->department?->faculty)
                @php
                    $deptList = $this->department->faculty->departments()->where('is_active', true)->orderBy('sort_order')->get();
                @endphp
                @if($deptList->isNotEmpty())
                    <section class="rounded-xl" aria-labelledby="sb-departments">
                        <h3 id="sb-departments" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                            <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Departments
                        </h3>
                        <ul class="border-l border-[#A7A9AC]" role="list">
                            @foreach($deptList as $dept)
                                @php $active = $this->department && $dept->id === $this->department->id; @endphp
                                <li>
                                    <a href="{{ route('department.show', ['faculty_short_name' => strtolower($this->department->faculty->short_name), 'department_code' => strtolower($dept->code)]) }}" wire:navigate
                                       class="group flex w-full items-center justify-between gap-2 border-l-[3px] pl-5 pr-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'border-diu-accent font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                        <span class="truncate">{{ $dept->name }}</span>
                                        <svg class="w-3.5 h-3.5 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endif

            {{-- Filters narrow the teacher list, so they are hidden while the
                 contacts view is up — the faculty and department navigation
                 above stays, which is the reason contacts live in this layout. --}}
            <!-- Designations -->
            @if($this->view !== 'contact' && $this->visibleDesignations->isNotEmpty())
                <section class="rounded-xl" aria-labelledby="sb-designations">
                    <h3 id="sb-designations" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Academic Designations
                    </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        <li>
                            <button type="button" wire:click="setDesignation(null)"
                                class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (! $this->designationId) ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                All Designations
                            </button>
                        </li>
                        @foreach($this->visibleDesignations as $desig)
                            <li>
                                <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                    class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ ($this->designationId == $desig->id) ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                    {{ $desig->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <!-- Administrative Roles -->
            @if($this->view !== 'contact' && $this->visibleAdminRoles->isNotEmpty())
                <section class="rounded-xl" aria-labelledby="sb-roles">
                    <h3 id="sb-roles" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                        Administrative Roles
                    </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        <li>
                            <button type="button" wire:click="setAdmin(null)"
                                class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ (! $this->adminRoleId) ? 'border-diu-accent font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                All Roles
                            </button>
                        </li>
                        @foreach($this->visibleAdminRoles as $role)
                            <li>
                                <button type="button" wire:click="setAdmin({{ $role->id }})"
                                    class="block w-full text-left border-l-[3px] px-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ ($this->adminRoleId == $role->id) ? 'border-diu-accent font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                    {{ $role->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($this->view !== 'contact' && ($this->designationId || $this->adminRoleId))
                <a href="{{ $this->department ? route('department.show', ['faculty_short_name' => strtolower($this->department->faculty->short_name), 'department_code' => strtolower($this->department->code)]) : route('home') }}" wire:navigate
                   class="block w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
                    Clear all filters
                </a>
            @endif
        </div>
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        @if($this->department)
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="eyebrow">Department</span>
                    <h2 class="section-title mt-2" style="color: var(--text-strong);">{{ $this->department->name }}</h2>
                    <p class="count mt-2">{{ $this->totalMembers }} faculty members</p>
                </div>

                @if($this->department->faculty)
                    @php
                        $deptRoute = [
                            'faculty_short_name' => strtolower($this->department->faculty->short_name),
                            'department_code' => strtolower($this->department->code),
                        ];
                    @endphp

                    {{-- Two views of the same department, switched here. Both are
                         real URLs, so they can be linked and the back button
                         works; wire:navigate makes the swap feel like a tab. --}}
                    <nav class="flex items-baseline gap-5 text-[13px] font-semibold">
                        <a href="{{ route('department.show', $deptRoute) }}" wire:navigate
                           class="pb-1 border-b-2 transition-colors"
                           style="{{ $this->view === 'contact'
                                ? 'border-color: transparent; color: var(--text-muted);'
                                : 'border-color: var(--color-diu-primary); color: var(--color-diu-primary);' }}">
                            Faculty members
                        </a>
                        <a href="{{ route('department.contact', $deptRoute) }}" wire:navigate
                           class="pb-1 border-b-2 transition-colors"
                           style="{{ $this->view === 'contact'
                                ? 'border-color: var(--color-diu-primary); color: var(--color-diu-primary);'
                                : 'border-color: transparent; color: var(--text-muted);' }}">
                            Contact
                        </a>
                    </nav>
                @endif
            </div>
        @endif

        @if($this->view === 'contact')

            {{-- The contact directory, rendered in the department's own layout so
                 the faculty and department sidebar stays put — that is the whole
                 point of it living here rather than on a page of its own. --}}
            @include('frontend.themes.theme_portrait.partials.department_contacts', [
                'contacts' => $this->contacts,
                'department' => $this->department,
            ])

        @else

        <!-- Instant search input -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="q"
                placeholder="Search teachers in this department by name, email, employee ID..."
                class="block w-full pl-9 pr-12 py-3 text-[15px] bg-transparent border-0 border-b-2 focus:outline-none focus:border-diu-primary transition-colors placeholder:text-slate-400" style="border-color: var(--border-soft); color: var(--text-strong);"
            />
            @if($q)
                <button wire:click="clearSearch" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                    Clear
                </button>
            @endif
        </div>

        <div>
            <h3 class="section-title" style="color: var(--text-strong);">
                {{ count($this->adminTeachers) + $this->teachers->total() }} Result{{ (count($this->adminTeachers) + $this->teachers->total()) === 1 ? '' : 's' }}
                @if($q)
                    for <span class="text-diu-primary">"{{ $q }}"</span>
                @endif
            </h3>
        </div>

        @if(count($this->adminTeachers) === 0 && $this->teachers->total() === 0)
            <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
                <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <p class="text-gray-500 font-semibold">No teachers found.</p>
                <p class="text-xs text-slate-400 mt-1">Try a different keyword or clear the active filters.</p>
            </div>
        @else
            @if(count($this->adminTeachers) > 0)
                <!-- Administrative Members Section -->
                <div class="space-y-4 mb-8">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-1 bg-diu-accent rounded-xs"></div>
                        <h4 class="font-display font-bold text-md text-gray-800">Administration</h4>
                    </div>
                    <div class="gallery">
                        @foreach($this->adminTeachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_portrait.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                    'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
                                ])
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->teachers->total() > 0)
                <!-- General Faculty Members Section -->
                <div class="space-y-4">
                    @if(count($this->adminTeachers) > 0)
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-1 bg-diu-primary rounded-xs"></div>
                            <h4 class="font-display font-bold text-md text-gray-800">Faculty Members</h4>
                        </div>
                    @endif
                    <div class="gallery">
                        @foreach($this->teachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_portrait.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                    'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
                                    'showAdminRole' => false,
                                ])
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $this->teachers->links('frontend.themes.theme_portrait.partials.pagination') }}
                    </div>
                </div>
            @endif
        @endif

        @endif
    </div>
</div>
