@php
    $currentFaculty = $currentFaculty ?? null;
    $currentDepartment = $currentDepartment ?? null;
    $isHome = $isHome ?? false;
    $sticky = $sticky ?? false;

    // Base route + params for the current context (faculty page or department page).
    // $hasBase guards route generation because faculties.short_name is nullable.
    $hasBase = (bool) ($currentFaculty->short_name ?? null);
    $baseRoute = $currentDepartment ? 'department.show' : 'faculty.show';
    $baseParams = ['faculty_short_name' => strtolower($currentFaculty->short_name ?? '')];
    if ($currentDepartment) {
        $baseParams['department_code'] = strtolower($currentDepartment->code);
    }
@endphp

<aside class="lg:col-span-1 space-y-5">
    <!-- Academic Faculties -->
    <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-primary/5">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
            Academic Faculties
        </h3>
        <div class="space-y-1.5">
            @foreach($faculties as $fac)
                @php $active = $currentFaculty && strtolower($fac->short_name ?? '') === strtolower($currentFaculty->short_name ?? ''); @endphp
                <a href="{{ $fac->url }}"
                   class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between {{ $active ? 'bg-diu-primary/15 text-diu-primary shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                    <span class="truncate">{{ $fac->name }}</span>
                    <span class="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">{{ $fac->code }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Departments submenu when a faculty is active -->
    @if(!$isHome && $currentFaculty && $departments->isNotEmpty())
        <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-accent/5">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Departments
            </h3>
            <div class="space-y-1">
                @foreach($departments as $dept)
                    @php
                        $dActive = $currentDepartment && strtolower($dept->code) === strtolower($currentDepartment->code);
                        $deptUrl = $hasBase
                            ? route('department.show', ['faculty_short_name' => strtolower($currentFaculty->short_name), 'department_code' => strtolower($dept->code)])
                            : '#';
                    @endphp
                    <a href="{{ $deptUrl }}"
                       class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between {{ $dActive ? 'bg-diu-accent/15 text-diu-accent font-bold shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                        <span class="truncate">{{ $dept->name }}</span>
                        <svg class="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Designations & Administrative Roles -->
    @if(!$isHome && $currentFaculty && ($adminRoles->isNotEmpty() || $designations->isNotEmpty()))
        <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-4 {{ $sticky ? 'sticky top-28' : '' }}">

            <!-- Administrative Roles -->
            @if($adminRoles->isNotEmpty())
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                        Administrative Roles
                    </h3>
                    <div class="space-y-1">
                        <a href="{{ $hasBase ? route($baseRoute, $baseParams) : '#' }}"
                           class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('admin')) ? 'bg-diu-accent/10 text-diu-accent font-bold border-l-2 border-diu-accent pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                            <div class="w-1.5 h-1.5 rounded-full {{ (!request('admin')) ? 'bg-diu-accent' : 'bg-slate-300' }}"></div>
                            All Roles
                        </a>
                        @foreach($adminRoles as $role)
                            <a href="{{ $hasBase ? route($baseRoute, $baseParams + ['admin' => $role->id]) : '#' }}"
                               class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (request('admin') == $role->id) ? 'bg-diu-accent/10 text-diu-accent font-bold border-l-2 border-diu-accent pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                <div class="w-1.5 h-1.5 rounded-full bg-diu-accent"></div>
                                {{ $role->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <hr class="border-white/50">

            <!-- Academic Designations -->
            @if($designations->isNotEmpty())
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Academic Designations
                    </h3>
                    <div class="space-y-1">
                        <a href="{{ $hasBase ? route($baseRoute, $baseParams) : '#' }}"
                           class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('designation')) ? 'bg-diu-primary/10 text-diu-primary font-bold border-l-2 border-diu-primary pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                            <div class="w-1.5 h-1.5 rounded-full {{ (!request('designation')) ? 'bg-diu-primary' : 'bg-slate-300' }}"></div>
                            All Designations
                        </a>
                        @foreach($designations as $desig)
                            <a href="{{ $hasBase ? route($baseRoute, $baseParams + ['designation' => $desig->id]) : '#' }}"
                               class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (request('designation') == $desig->id) ? 'bg-diu-primary/10 text-diu-primary font-bold border-l-2 border-diu-primary pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                <div class="w-1.5 h-1.5 rounded-full bg-diu-primary"></div>
                                {{ $desig->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    @endif
</aside>
