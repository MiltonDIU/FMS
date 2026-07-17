@php
    use App\Helpers\Branding;
    $brand = Branding::all();
@endphp
<header class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-sm">
    <!-- Top micro-bar -->
    <div class="bg-gradient-to-r from-diu-primary-dark to-diu-secondary-dark text-white text-[11px] font-sans tracking-wide py-1.5 px-4 md:px-8 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <span class="font-medium">{{ $brand['address_header'] }}</span>
            <span class="hidden sm:inline text-white/35">|</span>
            @if(! empty($brand['email']))
                <span class="hidden sm:inline text-white/80">Email: {{ $brand['email'] }}</span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="appearance-toggle"
                    class="group inline-flex items-center justify-center w-7 h-7 rounded-lg border border-white/25 text-white/90 hover:text-white hover:bg-white/15 transition-colors"
                    aria-label="Toggle dark mode" title="Toggle light / dark mode">
                {{-- Sun (shown in dark mode) --}}
                <svg class="w-4 h-4 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                {{-- Moon (shown in light mode) --}}
                <svg class="w-4 h-4 block dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>
            <a href="{{ $brand['main_site_url'] }}" target="_blank" rel="noopener noreferrer" class="hover:text-diu-accent-light transition-colors flex items-center gap-1.5 font-semibold">
                {{ $brand['main_site_label'] }}
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            </a>
        </div>
    </div>

    <!-- Main header block -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

            <!-- Branded Logo and Title -->
            <a href="{{ route('home') }}" class="flex items-center gap-3 cursor-pointer select-none">
                @if($brand['use_image_logo'])
                    <div class="relative flex items-center justify-center w-11 h-11 rounded-xl shadow-md overflow-hidden border-2 border-diu-accent bg-white">
                        <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['site_name'] }}" class="w-full h-full object-contain p-1" />
                    </div>
                @else
                    <div class="relative flex items-center justify-center w-11 h-11 bg-diu-primary text-white font-display font-extrabold text-2xl rounded-xl shadow-md overflow-hidden border-2 border-diu-accent">
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-diu-accent rotate-45"></div>
                        {{ $brand['monogram'] }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-display font-black text-xl text-diu-primary tracking-tight">{{ $brand['site_short_name'] }}</span>
                        <span class="bg-diu-secondary text-white text-[9px] font-sans font-bold px-2 py-0.5 rounded-xs tracking-wider uppercase">{{ $brand['badge_text'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-sans font-semibold uppercase tracking-widest">{{ $brand['tagline'] }}</p>
                </div>
            </a>

            <!-- Badge with count and Teacher Login Button -->
            <div class="flex items-center gap-3 justify-between md:justify-end">
                <div class="hidden lg:flex items-center gap-3 bg-slate-50 border border-slate-200/60 rounded-xl p-1.5 pl-3">
                    <div class="text-right">
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">{{ $brand['portal_label'] }}</p>
                        <p class="text-xs font-extrabold text-slate-700 font-sans">{{ $brand['portal_sublabel'] }}</p>
                    </div>
                    <span class="bg-diu-primary/10 text-diu-primary font-mono font-bold text-xs px-3 py-1.5 rounded-lg border border-diu-primary/10 shadow-3xs">
                        {{ $teachersCount }} {{ $brand['scholars_label'] }}
                    </span>
                </div>

                @if(auth()->check())
                    <a href="{{ url('/admin') }}" class="bg-diu-primary hover:bg-diu-primary-hover text-[color:var(--diu-on-primary)] text-xs font-bold px-4 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm border border-diu-primary/25 active:scale-95">
                        <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Dashboard</span>
                    </a>
                @else
                    <a href="{{ url('/admin/login') }}" class="bg-diu-primary hover:bg-diu-primary-hover text-[color:var(--diu-on-primary)] text-xs font-bold px-4 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm border border-diu-primary/25 active:scale-95">
                        <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>{{ $brand['login_label'] }}</span>
                    </a>
                @endif
            </div>

        </div>
    </div>

    <!-- Dynamic Statistics Bar -->
    <div class="bg-gradient-to-r from-diu-secondary to-diu-primary text-white py-2.5 text-xs shadow-inner">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-around md:justify-start gap-6 md:gap-12 font-medium">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                <span class="font-sans"><strong>{{ $facultiesCount }}</strong> {{ $brand['stat_faculties_label'] }}</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                <span class="font-sans"><strong>{{ $departmentsCount }}</strong> {{ $brand['stat_departments_label'] }}</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-diu-accent-light shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="font-sans"><strong>{{ $teachersCount }}</strong> {{ $brand['stat_profiles_label'] }}</span>
            </div>
        </div>
    </div>
</header>
