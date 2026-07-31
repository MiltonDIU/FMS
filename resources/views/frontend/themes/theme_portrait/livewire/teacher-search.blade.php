<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-1">
        <div class="lg:sticky lg:top-28 space-y-6">

            <!-- Academic Faculties -->
            <section class="rounded-xl" aria-labelledby="sb-faculties">
                <h3 id="sb-faculties" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    Academic Faculties
                </h3>
                <ul class="border-l border-[#A7A9AC]" role="list">
                    <li>
                        <button type="button" wire:click="selectFaculty(null)"
                            class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ ! $this->facultyId ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                            <span class="truncate">All Faculties</span>
                        </button>
                    </li>
                    @foreach($this->faculties as $fac)
                        @php $active = (string) $fac->id === (string) $this->facultyId; @endphp
                        <li>
                            <a href="{{ $fac->url }}" wire:navigate
                                class="group flex w-full items-center justify-between gap-2 border-l-[3px] px-3 py-2.5 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'border-diu-primary font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
                                <span class="truncate">{{ $fac->name }}</span>
                                <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm shrink-0">{{ $fac->teachers_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <!-- Departments (for selected faculty) -->
            @if($this->departments->isNotEmpty())
                <section class="rounded-xl" aria-labelledby="sb-departments">
                    <h3 id="sb-departments" class="eyebrow border-b pb-2 mb-3" style="border-color: var(--border-soft);">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        Departments
                    </h3>
                    <ul class="border-l border-[#A7A9AC]" role="list">
                        @foreach($this->departments as $dept)
                            @php $active = (string) $dept->id === (string) $this->departmentId; @endphp
                            <li>
                                <a href="{{ route('department.show', ['faculty_short_name' => strtolower($this->selectedFaculty->short_name), 'department_code' => strtolower($dept->code)]) }}" wire:navigate
                                    class="group flex w-full items-center justify-between gap-2 border-l-[3px] pl-5 pr-3 py-2 rounded-none text-[15px] font-medium transition-colors {{ $active ? 'border-diu-accent font-semibold' : 'border-transparent hover:border-diu-primary/40' }}">
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
            @if($this->visibleAdminRoles->isNotEmpty())
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

            <!-- Clear all -->
            @if($this->facultyId || $this->departmentId || $this->designationId || $this->adminRoleId)
                <a href="{{ route('home') }}" wire:navigate
                    class="block w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
                    Clear all filters
                </a>
            @endif
        </div>
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        @if($this->selectedFaculty)
            <div>
                <span class="eyebrow">Faculty Active</span>
                <h2 class="section-title mt-2" style="color: var(--text-strong);">{{ $this->selectedFaculty->name }}</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ $this->staticTeacherCount }} Faculty Members
                </p>
            </div>
        @else
            <div>
                <span class="eyebrow">All Faculties</span>
                <h2 class="section-title mt-2" style="color: var(--text-strong);">{{ \App\Helpers\Branding::get('site_name') }}</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ $this->staticTeacherCount }} Faculty Members
                </p>
            </div>
        @endif

        <!-- Instant search input -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="q"
                placeholder="Search teachers by name, email, department, faculty, designation..."
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
                                    'faculty' => $teacher->department->faculty,
                                    'department' => $teacher->department,
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
                                    'faculty' => $teacher->department->faculty,
                                    'department' => $teacher->department,
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
    </div>
</div>
