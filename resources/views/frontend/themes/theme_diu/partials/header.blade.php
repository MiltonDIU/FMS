<header class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-sm">
    <!-- Top micro-bar -->
    <div class="bg-gradient-to-r from-diu-primary-dark to-diu-secondary-dark text-white text-[11px] font-sans tracking-wide py-1.5 px-4 md:px-8 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <span class="font-medium">Smart City campus: Ashulia, Savar, Dhaka</span>
            <span class="hidden sm:inline text-white/35">|</span>
            <span class="hidden sm:inline text-white/80">Email: info@daffodilvarsity.edu.bd</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="https://daffodilvarsity.edu.bd" target="_blank" rel="noopener noreferrer" class="hover:text-diu-accent-light transition-colors flex items-center gap-1.5 font-semibold">
                Main Site
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            </a>
        </div>
    </div>

    <!-- Main header block -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

            <!-- Branded Logo and Title -->
            <a href="{{ route('home') }}" class="flex items-center gap-3 cursor-pointer select-none">
                <div class="relative flex items-center justify-center w-11 h-11 bg-diu-primary text-white font-display font-extrabold text-2xl rounded-xl shadow-md overflow-hidden border-2 border-diu-accent">
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-diu-accent rotate-45"></div>
                    D
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-display font-black text-xl text-diu-primary tracking-tight">DAFFODIL</span>
                        <span class="bg-diu-secondary text-white text-[9px] font-sans font-bold px-2 py-0.5 rounded-xs tracking-wider uppercase">Directory</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-sans font-semibold uppercase tracking-widest">International University, Bangladesh</p>
                </div>
            </a>

            <!-- Badge with count and Teacher Login Button -->
            <div class="flex items-center gap-3 justify-between md:justify-end">
                <div class="hidden lg:flex items-center gap-3 bg-slate-50 border border-slate-200/60 rounded-xl p-1.5 pl-3">
                    <div class="text-right">
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Academic Portal</p>
                        <p class="text-xs font-extrabold text-slate-700 font-sans">Active Directory</p>
                    </div>
                    <span class="bg-diu-primary/10 text-diu-primary font-mono font-bold text-xs px-3 py-1.5 rounded-lg border border-diu-primary/10 shadow-3xs">
                        {{ $teachersCount }} Scholars
                    </span>
                </div>

                <a href="{{ url('/admin/login') }}" class="bg-diu-primary hover:bg-diu-primary-hover text-[color:var(--diu-on-primary)] text-xs font-bold px-4 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm border border-diu-primary/25 active:scale-95">
                    <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span>Teacher Login</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Dynamic Statistics Bar -->
    <div class="bg-gradient-to-r from-diu-secondary to-diu-primary text-white py-2.5 text-xs shadow-inner">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-around md:justify-start gap-6 md:gap-12 font-medium">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                <span class="font-sans"><strong>{{ $facultiesCount }}</strong> Academic Faculties</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                <span class="font-sans"><strong>{{ $departmentsCount }}</strong> Departments</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="font-sans"><strong>{{ $teachersCount }}</strong> Faculty Profiles</span>
            </div>
        </div>
    </div>
</header>
