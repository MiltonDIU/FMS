<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-1 space-y-5">
        <!-- Academic Faculties -->
        <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-primary/5">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                Academic Faculties
            </h3>
            <div class="space-y-1.5">
                @foreach($this->faculties as $fac)
                    @php $active = (string) $fac->id === (string) $this->facultyId; @endphp
                    <button type="button" wire:click="setFaculty({{ $fac->id }})"
                        class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between gap-2 {{ $active ? 'bg-diu-primary/15 text-diu-primary shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                        <span class="truncate">{{ $fac->name }}</span>
                        <span class="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">{{ $fac->teachers_count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Departments (for selected faculty) -->
        @if($this->departments->isNotEmpty())
            <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-accent/5">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    Departments
                </h3>
                <div class="space-y-1">
                    @foreach($this->departments as $dept)
                        @php $active = (string) $dept->id === (string) $this->departmentId; @endphp
                        <button type="button" wire:click="setDepartment({{ $dept->id }})"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between gap-2 {{ $active ? 'bg-diu-accent/15 text-diu-accent font-bold shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                            <span class="truncate">{{ $dept->name }}</span>
                            <svg class="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Designations -->
        @if($this->visibleDesignations->isNotEmpty())
            <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Academic Designations
                    </h3>
                    <div class="space-y-1">
                        <button type="button" wire:click="setDesignation(null)"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (! $this->designationId) ? 'bg-diu-primary/10 text-diu-primary font-bold border-l-2 border-diu-primary pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                            <div class="w-1.5 h-1.5 rounded-full {{ (! $this->designationId) ? 'bg-diu-primary' : 'bg-slate-300' }}"></div>
                            All Designations
                        </button>
                        @foreach($this->visibleDesignations as $desig)
                            <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ ($this->designationId == $desig->id) ? 'bg-diu-primary/10 text-diu-primary font-bold border-l-2 border-diu-primary pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                <div class="w-1.5 h-1.5 rounded-full bg-diu-primary"></div>
                                {{ $desig->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Administrative Roles -->
        @if($this->visibleAdminRoles->isNotEmpty())
            <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                        Administrative Roles
                    </h3>
                    <div class="space-y-1">
                        <button type="button" wire:click="setAdmin(null)"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (! $this->adminRoleId) ? 'bg-diu-accent/10 text-diu-accent font-bold border-l-2 border-diu-accent pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                            <div class="w-1.5 h-1.5 rounded-full {{ (! $this->adminRoleId) ? 'bg-diu-accent' : 'bg-slate-300' }}"></div>
                            All Roles
                        </button>
                        @foreach($this->visibleAdminRoles as $role)
                            <button type="button" wire:click="setAdmin({{ $role->id }})"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ ($this->adminRoleId == $role->id) ? 'bg-diu-accent/10 text-diu-accent font-bold border-l-2 border-diu-accent pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                <div class="w-1.5 h-1.5 rounded-full bg-diu-accent"></div>
                                {{ $role->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Clear all -->
        @if($this->facultyId || $this->departmentId || $this->designationId || $this->adminRoleId)
            <button type="button" wire:click="setFaculty(null); setDepartment(null); setDesignation(null); setAdmin(null)"
                class="w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
                Clear all filters
            </button>
        @endif
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        <!-- Instant search input -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="q"
                placeholder="Search teachers by name, email, department, faculty, designation..."
                class="block w-full pl-10 pr-12 py-3 border border-slate-200 rounded-2xl text-sm bg-white/70 backdrop-blur-xs hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-primary focus:border-diu-primary transition-all placeholder:text-slate-400 shadow-sm"
            />
            @if($q)
                <button wire:click="clearSearch" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                    Clear
                </button>
            @endif
        </div>

        @if($this->selectedFaculty)
            <div>
                <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Faculty Active</span>
                <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $this->selectedFaculty->name }}</h2>
            </div>
        @endif

        <div>
            <h3 class="text-2xl font-extrabold text-gray-900 font-display">
                {{ $this->teachers->total() }} Result{{ $this->teachers->total() === 1 ? '' : 's' }}
                @if($q)
                    for <span class="text-diu-primary">"{{ $q }}"</span>
                @endif
            </h3>
        </div>

        @if($this->teachers->total() === 0)
            <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
                <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <p class="text-gray-500 font-semibold">No teachers found.</p>
                <p class="text-xs text-slate-400 mt-1">Try a different keyword or clear the active filters.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($this->teachers as $teacher)
                    @if($teacher->department)
                        @include('frontend.themes.theme_diu.partials.teacher_card', [
                            'teacher' => $teacher,
                            'faculty' => $teacher->department->faculty,
                            'department' => $teacher->department,
                        ])
                    @endif
                @endforeach
            </div>

            <div class="mt-6">
                {{ $this->teachers->links('frontend.themes.theme_diu.partials.pagination') }}
            </div>
        @endif
    </div>
</div>
