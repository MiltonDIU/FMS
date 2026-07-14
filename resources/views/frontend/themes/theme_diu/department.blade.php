<!DOCTYPE html>
<html lang="en">
<head>
    @include('frontend.themes.theme_diu.partials.head', ['title' => ($department->name ?? 'Department') . ' - Faculty Directory'])
</head>
<body class="bg-transparent min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    @include('frontend.themes.theme_diu.partials.header')

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

        <!-- Breadcrumb -->
        <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
            <a href="{{ url('/') }}" class="hover:text-diu-green transition">DIU Faculties</a>
            <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <a href="{{ url('/' . strtolower($faculty->short_name)) }}" class="hover:text-diu-green transition">{{ $faculty->short_name }}</a>
            <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="text-diu-green">{{ $department->name }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- LEFT SIDEBAR -->
            <aside class="lg:col-span-1 space-y-5">

                <!-- Academic Faculties -->
                <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-green/5">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                        Academic Faculties
                    </h3>
                    <div class="space-y-1.5">
                        @foreach($faculties as $fac)
                            @php $active = strtolower($fac->short_name) === strtolower($faculty->short_name); @endphp
                            <a href="{{ url('/' . strtolower($fac->short_name)) }}"
                               class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between {{ $active ? 'bg-diu-green/15 text-diu-green shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                                <span class="truncate">{{ $fac->name }}</span>
                                <span class="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">{{ $fac->code }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Departments submenu (current faculty) -->
                @if($faculty->departments->isNotEmpty())
                    <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-orange/5">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Departments
                        </h3>
                        <div class="space-y-1">
                            @foreach($faculty->departments as $dept)
                                @php $dActive = strtolower($dept->code) === strtolower($department->code); @endphp
                                <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($dept->code)) }}"
                                   class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between {{ $dActive ? 'bg-diu-orange/15 text-diu-orange font-bold shadow-xs' : 'hover:bg-white/40 text-slate-600 hover:text-slate-900' }}">
                                    <span class="truncate">{{ $dept->name }}</span>
                                    <svg class="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Designations & Administrative Roles -->
                @if($adminRoles->isNotEmpty() || $designations->isNotEmpty())
                    @php $base = url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code)); @endphp
                    <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-4 sticky top-28">

                        <!-- Administrative Roles -->
                        @if($adminRoles->isNotEmpty())
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                                    Administrative Roles
                                </h3>
                                <div class="space-y-1">
                                    <a href="{{ $base }}"
                                       class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('admin')) ? 'bg-diu-orange/10 text-diu-orange font-bold border-l-2 border-diu-orange pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                        <div class="w-1.5 h-1.5 rounded-full {{ (!request('admin')) ? 'bg-diu-orange' : 'bg-slate-300' }}"></div>
                                        All Roles
                                    </a>
                                    @foreach($adminRoles as $role)
                                        <a href="{{ $base }}?admin={{ $role->id }}"
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
                                    <a href="{{ $base }}"
                                       class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 {{ (!request('designation')) ? 'bg-diu-green/10 text-diu-green font-bold border-l-2 border-diu-green pl-2.5' : 'text-slate-500 hover:text-slate-800 hover:bg-white/30' }}">
                                        <div class="w-1.5 h-1.5 rounded-full {{ (!request('designation')) ? 'bg-diu-green' : 'bg-slate-300' }}"></div>
                                        All Designations
                                    </a>
                                    @foreach($designations as $desig)
                                        <a href="{{ $base }}?designation={{ $desig->id }}"
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
                <div>
                    <span class="text-[10px] bg-diu-green/10 text-diu-green font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Department Active</span>
                    <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $department->name }}</h2>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        {{ $teachers->total() }} Faculty Members
                    </p>
                </div>

                @if($teachers->total() === 0)
                    <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
                        <p class="text-gray-500 font-semibold">No faculty members found for the selected filter.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($teachers as $teacher)
                            @include('frontend.themes.theme_diu.partials.teacher_card', ['teacher' => $teacher, 'faculty' => $faculty, 'department' => $department])
                        @endforeach
                    </div>

                    {{ $teachers->links('frontend.themes.theme_diu.partials.pagination') }}
                @endif
            </div>

        </div>
    </main>

    @include('frontend.themes.theme_diu.partials.footer')

</body>
</html>
