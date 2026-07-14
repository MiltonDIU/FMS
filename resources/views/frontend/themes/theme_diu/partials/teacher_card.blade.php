@php
    $isAdmin = $teacher->is_administrative;
    $accent = $isAdmin ? 'diu-accent' : 'diu-primary';
    $initials = strtoupper(substr($teacher->first_name, 0, 1) . substr($teacher->last_name, 0, 1));
    $interests = $teacher->research_interests;
    $profileUrl = ($faculty->short_name && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ])
        : '#';
@endphp

<div class="bg-white/40 backdrop-blur-md rounded-xl border transition-all duration-300 overflow-hidden group flex flex-col justify-between cursor-pointer ring-1 {{ $isAdmin ? 'border-diu-accent/30 hover:border-diu-accent/60 ring-diu-accent/10 hover:ring-diu-accent/25 shadow-sm' : 'border-white/60 hover:border-white/95 ring-diu-primary/10 hover:ring-diu-primary/25 shadow-sm' }}">
    <div>
        <!-- Card Header Top Accent color -->
        <div class="h-1.5 {{ $isAdmin ? 'bg-diu-accent' : 'bg-diu-primary' }}"></div>

        <div class="p-5">
            <!-- Main info row -->
            <div class="flex items-start gap-4">
                <!-- Avatar or customized fallback image -->
                <div class="relative w-16 h-16 shrink-0 rounded-full overflow-hidden border-2 border-white shadow-md">
                    @if($teacher->photo)
                        <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                    @else
                        <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-lg">
                            {{ $initials }}
                        </div>
                    @endif
                </div>

                <!-- Basic Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                        @if($isAdmin)
                            <span class="inline-flex items-center gap-1 bg-diu-accent/15 text-diu-accent text-[9px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-sm border border-diu-accent/25">
                                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                {{ optional($teacher->designation)->name }}
                            </span>
                        @endif
                    </div>
                    <h4 class="text-sm font-semibold text-slate-800 tracking-tight leading-snug line-clamp-1 group-hover:text-diu-primary transition-colors">
                        {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                    </h4>
                    <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">{{ optional($teacher->designation)->name ?? 'Faculty Member' }}</p>
                    <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-0.5">{{ optional($teacher->department)->name ?? 'General' }}</p>
                </div>
            </div>

            <!-- Core metadata/Details -->
            <div class="mt-5 space-y-2 border-t border-white/40 pt-3">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span class="truncate hover:text-diu-primary transition-colors font-mono">{{ $teacher->secondary_email ?? 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span class="font-sans">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span class="truncate font-sans leading-tight">{{ $teacher->office_room ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Research Interest Badge Row -->
            @if(count($interests) > 0)
                <div class="mt-4 flex flex-wrap gap-1">
                    @foreach(array_slice($interests, 0, 2) as $interest)
                        <span class="bg-white/40 text-slate-600 border border-white/60 text-[9px] font-sans px-2 py-0.5 rounded-sm">
                            {{ $interest }}
                        </span>
                    @endforeach
                    @if(count($interests) > 2)
                        <span class="bg-white/50 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm border border-white/60">
                            +{{ count($interests) - 2 }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Footer view profile link -->
    <a href="{{ $profileUrl }}" class="px-5 py-3.5 bg-white/30 border-t border-white/40 flex items-center justify-between group-hover:bg-white/60 transition-colors">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 {{ $isAdmin ? 'text-diu-accent' : 'text-diu-primary' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <span class="text-[10px] text-slate-400 font-semibold uppercase font-sans">
                {{ $teacher->publications->count() }} Publications
            </span>
        </div>
        <span class="text-xs font-semibold text-diu-primary group-hover:text-diu-accent flex items-center gap-1 transition-all">
            Profile
            <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </span>
    </a>
</div>
