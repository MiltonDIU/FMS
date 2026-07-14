<!DOCTYPE html>
<html lang="en">
<head>
    @include('frontend.themes.theme_diu.partials.head', ['title' => 'Daffodil International University Faculty Directory'])
</head>
<body class="bg-transparent min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    @include('frontend.themes.theme_diu.partials.header')

    @php
        // On the root URL ("/") we show the full faculties overview (mirrors the
        // Next.js home). The HomeController always sets a default $selectedFaculty,
        // so we detect the true home state from the request path instead.
        $isHome = trim(request()->path(), '/') === '';
        $q = trim(request('q'));
        $visibleFaculties = $faculties;
        if ($q) {
            $visibleFaculties = $faculties->filter(fn ($f) =>
                stripos($f->name, $q) !== false
                || stripos($f->description ?? '', $q) !== false
                || stripos($f->short_name, $q) !== false
                || stripos($f->code, $q) !== false
            );
        }

        // When a faculty is selected, load its teachers (with designation + department)
        // so the main stage can render teacher cards directly on the home page.
        // Paginated (12/page) so the page does not grow too long.
        // Designation + Administrative Role filters narrow the list.
        $teachers = collect();
        $designations = collect();
        $adminRoles = collect();
        if (!$isHome && $selectedFaculty) {
            $teachersQ = $selectedFaculty->teachers()
                ->where('teachers.is_active', true)
                ->where('teachers.is_archived', false);

            if ($desig = request('designation')) {
                $teachersQ->where('teachers.designation_id', $desig);
            }
            if ($admin = request('admin')) {
                $adminTeacherIds = \DB::table('administrative_role_user')
                    ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                    ->join('departments', 'departments.id', '=', 'teachers.department_id')
                    ->where('departments.faculty_id', $selectedFaculty->id)
                    ->where('administrative_role_user.administrative_role_id', $admin)
                    ->pluck('teachers.id');
                $teachersQ->whereIn('teachers.id', $adminTeacherIds);
            }

            $teachers = $teachersQ->with(['designation', 'department'])
                ->paginate(12)
                ->withQueryString();

            // Keep management roles on top within the current page.
            $teachers->getCollection()->sortBy(fn ($t) => (optional($t->designation)->sort_order ?? 999) * 1000 + ($t->sort_order ?? 0));

            // Distinct designations present in this faculty.
            $designationIds = \DB::table('teachers')
                ->join('departments', 'departments.id', '=', 'teachers.department_id')
                ->where('departments.faculty_id', $selectedFaculty->id)
                ->where('teachers.is_active', true)
                ->where('teachers.is_archived', false)
                ->whereNotNull('teachers.designation_id')
                ->distinct()
                ->pluck('teachers.designation_id');
            $designations = \App\Models\Designation::whereIn('id', $designationIds)->orderBy('sort_order')->get();

            // Distinct administrative roles present in this faculty (via the
            // administrative_role_user pivot, keyed by the teacher's user_id).
            $adminRoleIds = \DB::table('administrative_role_user')
                ->join('teachers', 'teachers.user_id', '=', 'administrative_role_user.user_id')
                ->join('departments', 'departments.id', '=', 'teachers.department_id')
                ->where('departments.faculty_id', $selectedFaculty->id)
                ->whereNotNull('administrative_role_user.administrative_role_id')
                ->distinct()
                ->pluck('administrative_role_user.administrative_role_id');
            $adminRoles = \App\Models\AdministrativeRole::whereIn('id', $adminRoleIds)->orderBy('sort_order')->get();
        }
    @endphp

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

        <!-- Breadcrumb Navigation Strip -->
        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-sans py-2.5 px-5 bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm mb-6">
            <a href="{{ url('/') }}" class="hover:text-diu-green font-semibold transition-colors">DIU Faculties</a>
            @if(!$isHome && $selectedFaculty)
                <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                <span class="text-diu-green font-semibold">{{ $selectedFaculty->short_name }}</span>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- LEFT SIDEBAR: Academic Faculties -->
            <aside class="lg:col-span-1 space-y-5">
                <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-green/5">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                        Academic Faculties
                    </h3>
                    <div class="space-y-1.5">
                        @foreach($faculties as $fac)
                            @php $active = !$isHome && $selectedFaculty && strtolower($selectedFaculty->short_name) === strtolower($fac->short_name); @endphp
                            <a href="{{ url('/' . strtolower($fac->short_name)) }}"
                               class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between {{ $active ? 'bg-diu-green/15 text-diu-green shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                                <span class="truncate">{{ $fac->name }}</span>
                                <span class="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">{{ $fac->code }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Departments submenu when a faculty is active -->
                @if(!$isHome && $selectedFaculty && $departments->isNotEmpty())
                    <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-orange/5">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Departments
                        </h3>
                        <div class="space-y-1">
                            @foreach($departments as $dept)
                                <a href="{{ url('/' . strtolower($selectedFaculty->short_name) . '/' . strtolower($dept->code)) }}"
                                   class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between hover:bg-white/40 text-slate-600 hover:text-slate-900">
                                    <span class="truncate">{{ $dept->name }}</span>
                                    <svg class="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Designations & Administrative Roles (single block, two titled sections) -->
                @if(!$isHome && $selectedFaculty && ($adminRoles->isNotEmpty() || $designations->isNotEmpty()))
                    <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-4">

                        <!-- Administrative Roles -->
                        @if($adminRoles->isNotEmpty())
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                                    Administrative Roles
                                </h3>
                                <div class="space-y-1">
                                    <a href="{{ url('/' . strtolower($selectedFaculty->short_name)) }}"
                                       class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('admin')) ? 'bg-diu-orange/10 text-diu-orange font-bold border-l-2 border-diu-orange pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                        <div class="w-1.5 h-1.5 rounded-full {{ (!request('admin')) ? 'bg-diu-orange' : 'bg-slate-300' }}"></div>
                                        All Roles
                                    </a>
                                    @foreach($adminRoles as $role)
                                        <a href="{{ url('/' . strtolower($selectedFaculty->short_name) . '?admin=' . $role->id) }}"
                                           class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (request('admin') == $role->id) ? 'bg-diu-orange/10 text-diu-orange font-bold border-l-2 border-diu-orange pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                            <div class="w-1.5 h-1.5 rounded-full bg-diu-orange"></div>
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
                                    <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    Academic Designations
                                </h3>
                                <div class="space-y-1">
                                    <a href="{{ url('/' . strtolower($selectedFaculty->short_name)) }}"
                                       class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('designation')) ? 'bg-diu-green/10 text-diu-green font-bold border-l-2 border-diu-green pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                        <div class="w-1.5 h-1.5 rounded-full {{ (!request('designation')) ? 'bg-diu-green' : 'bg-slate-300' }}"></div>
                                        All Designations
                                    </a>
                                    @foreach($designations as $desig)
                                        <a href="{{ url('/' . strtolower($selectedFaculty->short_name) . '?designation=' . $desig->id) }}"
                                           class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (request('designation') == $desig->id) ? 'bg-diu-green/10 text-diu-green font-bold border-l-2 border-diu-green pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                            <div class="w-1.5 h-1.5 rounded-full bg-diu-green"></div>
                                            {{ $desig->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>
                @endif
            </aside>

            <!-- RIGHT MAIN STAGE -->
            <div class="lg:col-span-3 space-y-6">

                @if($isHome)
                    <!-- MAIN INTRO BANNER -->
                    @if(!$q)
                        <div class="bg-gradient-to-br from-diu-green-dark via-diu-green to-diu-blue border border-white/20 backdrop-blur-md p-6 md:p-8 rounded-2xl text-white shadow-lg relative overflow-hidden mb-8">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20 blur-3xl pointer-events-none"></div>
                            <div class="absolute bottom-0 left-0 w-32 h-32 bg-diu-orange/20 rounded-full -ml-12 -mb-12 blur-2xl pointer-events-none"></div>
                            <div class="relative z-10 max-w-xl">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.5c-1.8 0-3.5 1.4-3.5 3.5s1.7 3.5 3.5 3.5 3.5-1.4 3.5-3.5a2 2 0 0 0-.063-.5"/><path d="M10 10a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M4.5 11h.5a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M6 17.5a2 2 0 0 0 2 2c1.8 0 3.5-1.4 3.5-3.5S9.8 12.5 8 12.5a2 2 0 0 0-2 2c0 .7.3 1.3.5 1.5Z"/><path d="m15 8 .5.5a2 2 0 0 1 0 2.8l-3 3a2 2 0 0 1-2.8 0l-.5-.5"/><path d="m13 14-.5-.5a2 2 0 0 1 0-2.8l3-3a2 2 0 0 1 2.8 0l.5.5"/></svg>
                                    <span class="text-[10px] uppercase font-bold tracking-widest text-diu-orange">Smart Academic Portal</span>
                                </div>
                                <h2 class="text-xl md:text-2xl font-display font-extrabold leading-tight tracking-tight">
                                    Daffodil International University Faculty Directory
                                </h2>
                                <p class="text-xs text-white/85 font-sans mt-2.5 leading-relaxed">
                                    Welcome to the official, modernized Scholar profile portal. Explore academic credentials, award catalogs, research interest matrices, and generate citations for publication details.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- HOME PAGE: Explore by Academic Faculties -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-1 bg-diu-green rounded-xs"></div>
                            <h3 class="font-display font-bold text-md text-gray-800">
                                {{ $q ? 'Search results for "' . $q . '"' : 'Explore by Academic Faculties' }}
                            </h3>
                        </div>

                        @if($visibleFaculties->isEmpty())
                            <div class="text-center py-16 bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl shadow-sm">
                                <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <h4 class="text-sm font-bold text-slate-800 font-display">No faculties found</h4>
                                <p class="text-xs text-slate-500 font-sans max-w-sm mx-auto mt-1 leading-relaxed">No faculties match your search. Try a different keyword.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @foreach($visibleFaculties as $faculty)
                                    @php
                                        $deptCount = $faculty->departments->count();
                                        $memberCount = $faculty->teachers()->count();
                                    @endphp
                                    <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm hover:shadow-xl hover:border-white/80 transition-all duration-300 overflow-hidden group flex flex-col justify-between ring-1 ring-diu-green/10 hover:ring-diu-green/25">
                                        <!-- Visual Header -->
                                        <div class="relative h-44 overflow-hidden bg-gradient-to-br from-diu-green-dark via-diu-green to-diu-blue">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                            <div class="absolute top-4 right-4 bg-diu-orange text-white text-xs font-display font-extrabold px-2.5 py-1 rounded-md shadow-md tracking-wider">
                                                {{ $faculty->code }}
                                            </div>
                                            <div class="absolute bottom-4 left-4 right-4">
                                                <h3 class="text-white font-display font-bold text-lg leading-snug drop-shadow-xs">
                                                    {{ $faculty->name }}
                                                </h3>
                                            </div>
                                        </div>

                                        <div class="p-5 flex-1 flex flex-col justify-between">
                                            <p class="text-xs text-slate-500 font-sans leading-relaxed mb-4 line-clamp-2">
                                                {{ $faculty->description ?? 'Academic faculty of Daffodil International University.' }}
                                            </p>

                                            <div class="pt-4 border-t border-white/40 flex items-center justify-between">
                                                <div class="flex gap-4">
                                                    <div class="flex items-center gap-1 text-slate-600">
                                                        <svg class="w-3.5 h-3.5 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                                                        <span class="text-xs font-medium">{{ $deptCount }} Depts</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-slate-600">
                                                        <svg class="w-3.5 h-3.5 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                        <span class="text-xs font-medium">{{ $memberCount }} Members</span>
                                                    </div>
                                                </div>

                                                <a href="{{ url('/' . strtolower($faculty->short_name)) }}" class="text-xs font-semibold text-diu-green hover:text-diu-orange flex items-center gap-1 transition-colors group-hover:translate-x-1 duration-200">
                                                    Explore
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <!-- FACULTY SELECTED: Teachers of the faculty -->
                    <div class="space-y-6">
                        <div>
                            <span class="text-[10px] bg-diu-green/10 text-diu-green font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Faculty Active</span>
                            <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $selectedFaculty->name }}</h2>
                            <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                                <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                {{ $teachers->total() }} Faculty Members
                            </p>
                        </div>

                        @if($teachers->total() === 0)
                            <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
                                <p class="text-gray-500 font-semibold">No faculty members found under this faculty.</p>
                            </div>
                        @else
                            <!-- Faculty Members (paginated) -->
                            <div class="space-y-6">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center">
                                    <span class="w-1.5 h-4 bg-diu-green rounded-full mr-2"></span>
                                    Departmental Faculty Members
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach($teachers as $teacher)
                                        @if($teacher->department)
                                            @include('frontend.themes.theme_diu.partials.teacher_card', ['teacher' => $teacher, 'faculty' => $selectedFaculty, 'department' => $teacher->department])
                                        @endif
                                    @endforeach
                                </div>

                                {{ $teachers->links('frontend.themes.theme_diu.partials.pagination') }}
                            </div>

                            <!-- Departments under this Faculty -->
                            @if($departments->isNotEmpty())
                                <div class="space-y-4 pt-8 border-t border-slate-200/60 mt-8">
                                    <div class="flex items-center gap-2">
                                        <div class="h-4 w-1 bg-diu-orange rounded-xs"></div>
                                        <h4 class="font-display font-bold text-sm text-gray-800">Departments under this Faculty</h4>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                        @foreach($departments as $dept)
                                            <a href="{{ url('/' . strtolower($selectedFaculty->short_name) . '/' . strtolower($dept->code)) }}"
                                               class="group bg-white/60 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm ring-1 ring-diu-orange/5 p-5 text-left hover:shadow-md hover:border-diu-orange/30 transition-all duration-200">
                                                <div class="flex items-start justify-between gap-3 mb-3">
                                                    <div class="w-10 h-10 rounded-xl bg-diu-orange/10 flex items-center justify-center shrink-0 group-hover:bg-diu-orange/20 transition-colors">
                                                        <svg class="w-5 h-5 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                                    </div>
                                                    <span class="bg-diu-orange/10 text-diu-orange text-[10px] font-bold px-2 py-1 rounded-md font-mono shrink-0">{{ $dept->short_name ?? $dept->code }}</span>
                                                </div>
                                                <h5 class="text-xs font-display font-bold text-gray-800 leading-tight line-clamp-2 group-hover:text-diu-orange transition-colors">{{ $dept->name }}</h5>
                                                <div class="mt-2.5 flex items-center gap-1 text-[10px] text-slate-400 font-sans">
                                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                    <span>View department scholars</span>
                                                    <svg class="w-3 h-3 ml-auto opacity-0 group-hover:opacity-100 transition-opacity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </main>

    @include('frontend.themes.theme_diu.partials.footer')

</body>
</html>
