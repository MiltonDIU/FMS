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
                <button type="button" wire:click="toggleAll"
                    class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between {{ $this->all ? 'bg-diu-primary/15 text-diu-primary shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                    <span class="truncate">All Faculties</span>
                </button>
                @php
                    $faculties = \App\Models\Faculty::where('is_active', true)->orderBy('sort_order')->get();
                @endphp
                @foreach($faculties as $fac)
                    @php $active = ! $this->all && $this->department && $fac->id === $this->department->faculty_id; @endphp
                    <a href="{{ $fac->url }}" wire:navigate
                        class="block w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between gap-2 {{ $active ? 'bg-diu-primary/15 text-diu-primary shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                            <span class="truncate">{{ $fac->name }}</span>
                            <span class="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">{{ $fac->code }}</span>
                        </a>
                @endforeach
            </div>
        </div>

        <!-- Departments (for selected faculty) -->
        @if($this->department?->faculty)
            @php
                $deptList = $this->department->faculty->departments()->where('is_active', true)->orderBy('sort_order')->get();
            @endphp
            @if($deptList->isNotEmpty())
                <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-accent/5">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        Departments
                    </h3>
                    <div class="space-y-1">
                        @foreach($deptList as $dept)
                            @php $active = $this->department && $dept->id === $this->department->id; @endphp
                            <a href="{{ route('department.show', ['faculty_short_name' => strtolower($this->department->faculty->short_name), 'department_code' => strtolower($dept->code)]) }}" wire:navigate
                                class="block w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between gap-2 {{ $active ? 'bg-diu-accent/15 text-diu-accent font-bold shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                                <span class="truncate">{{ $dept->name }}</span>
                                <svg class="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
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
        @if($this->designationId || $this->adminRoleId)
            <a href="{{ $this->department ? route('department.show', ['faculty_short_name' => strtolower($this->department->faculty->short_name), 'department_code' => strtolower($this->department->code)]) : route('home') }}" wire:navigate
                class="block w-full text-[11px] font-semibold text-slate-400 hover:text-diu-primary transition-colors pt-2">
                Clear all filters
            </a>
        @endif
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        @if($this->department)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Department Active</span>
                    <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $this->department->name }}</h2>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        {{ $this->totalMembers }} Faculty Members
                    </p>
                </div>

                @if($this->department->faculty)
                    <a href="{{ route('department.contact', ['faculty_short_name' => strtolower($this->department->faculty->short_name), 'department_code' => strtolower($this->department->code)]) }}"
                       wire:navigate
                       class="inline-flex items-center gap-2 bg-diu-primary hover:bg-diu-primary-dark text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Contact Us
                    </a>
                @endif
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
                placeholder="Search teachers in this department by name, email, employee ID..."
                class="block w-full pl-10 pr-12 py-3 border border-slate-200 rounded-2xl text-sm bg-white/70 backdrop-blur-xs hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-primary focus:border-diu-primary transition-all placeholder:text-slate-400 shadow-sm"
            />
            @if($q)
                <button wire:click="clearSearch" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                    Clear
                </button>
            @endif
        </div>

        <div>
            <h3 class="text-2xl font-extrabold text-gray-900 font-display">
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($this->adminTeachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_default.partials.teacher_card', [
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($this->teachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_default.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                    'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
                                    'showAdminRole' => false,
                                ])
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $this->teachers->links('frontend.themes.theme_default.partials.pagination') }}
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
