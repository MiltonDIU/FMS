<div class="grid grid-cols-1 lg:grid-cols-4 gap-6" x-data="{ open: false }">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-1">
        <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm p-4 sticky top-24 space-y-5">
            <!-- Faculties -->
            <div>
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                    <span class="w-1 h-3 bg-diu-primary rounded-full"></span> Faculties
                </h4>
                <ul class="space-y-1">
                    @foreach($this->faculties as $fac)
                        @php $active = (string) $fac->id === (string) $this->facultyId; @endphp
                        <li>
                            <button type="button" wire:click="setFaculty({{ $fac->id }})"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-all flex items-center justify-between gap-2 {{ $active ? 'bg-diu-primary text-white shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-diu-primary' }}">
                                <span class="truncate">{{ $fac->short_name ?? $fac->name }}</span>
                                <span class="{{ $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-400' }} text-[9px] font-bold px-1.5 py-0.5 rounded-full">{{ $fac->teachers_count }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Departments (for selected faculty) -->
            @if($this->departments->isNotEmpty())
                <div class="pt-4 border-t border-white/50">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                        <span class="w-1 h-3 bg-diu-accent rounded-full"></span> Departments
                    </h4>
                    <ul class="space-y-1 max-h-56 overflow-y-auto pr-1 custom-scroll">
                        @foreach($this->departments as $dept)
                            @php $active = (string) $dept->id === (string) $this->departmentId; @endphp
                            <li>
                                <button type="button" wire:click="setDepartment({{ $dept->id }})"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-all flex items-center gap-2 {{ $active ? 'bg-diu-accent text-white shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-diu-accent' }}">
                                    <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                    <span class="truncate">{{ $dept->name }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Designations -->
            @if($this->visibleDesignations->isNotEmpty())
                <div class="pt-4 border-t border-white/50">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                        <span class="w-1 h-3 bg-diu-secondary rounded-full"></span> Designations
                    </h4>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($this->visibleDesignations as $desig)
                            @php $active = (string) $desig->id === (string) $this->designationId; @endphp
                            <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-full border transition-all {{ $active ? 'bg-diu-secondary text-white border-diu-secondary' : 'bg-white/60 text-slate-600 border-white/80 hover:border-diu-secondary/40 hover:text-diu-secondary' }}">
                                {{ $desig->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Administrative Roles -->
            @if($this->visibleAdminRoles->isNotEmpty())
                <div class="pt-4 border-t border-white/50">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                        <span class="w-1 h-3 bg-diu-accent rounded-full"></span> Administrative
                    </h4>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($this->visibleAdminRoles as $role)
                            @php $active = (string) $role->id === (string) $this->adminRoleId; @endphp
                            <button type="button" wire:click="setAdmin({{ $role->id }})"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-full border transition-all {{ $active ? 'bg-diu-accent text-white border-diu-accent' : 'bg-white/60 text-slate-600 border-white/80 hover:border-diu-accent/40 hover:text-diu-accent' }}">
                                {{ $role->name }}
                            </button>
                        @endforeach
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
        </div>
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
