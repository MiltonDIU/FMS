@php
    $pageDeptId = $department?->id;
    $pageFacId = $faculty?->id;

    $showAdminRole = $showAdminRole ?? true;
    $adminRole = null;
    if ($teacher->administrativeRoles->isNotEmpty()) {
        $roles = $teacher->administrativeRoles;
        if ($pageDeptId && $roles->where('department_id', $pageDeptId)->isNotEmpty()) {
            $adminRole = $roles->where('department_id', $pageDeptId)->first();
        } elseif ($pageFacId) {
            $adminRole = $roles->where('faculty_id', $pageFacId)->where('department_id', null)->first();
        } else {
            $adminRole = $roles->first();
        }
    }
    $isAdmin = $showAdminRole && ! is_null($adminRole);
    $adminRoleName = $adminRole?->administrativeRole?->name;

    $initials = strtoupper(substr($teacher->first_name, 0, 1) . substr($teacher->last_name, 0, 1));
    $interests = $teacher->research_interests;
    $teachingAreas = $teacher->teachingAreas;
    $areaCount = $teachingAreas->count();
    $profileUrl = ($faculty->short_name && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ])
        : '#';
@endphp

@if($isAdmin)
<div class="group flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-diu-accent/30 transition-all duration-300 overflow-hidden">
    <div>
        <div class="relative h-20 bg-gradient-to-r from-diu-accent to-diu-accent-hover">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 16px 16px;"></div>
            <div class="absolute top-3 left-4 inline-flex items-center gap-1 bg-white/20 text-white text-[9px] font-sans font-bold uppercase px-2 py-0.5 rounded-sm backdrop-blur-sm">
                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                {{ $adminRoleName }}
            </div>
            <div class="absolute -bottom-8 left-5 w-16 h-16 rounded-xl overflow-hidden bg-white p-1 shadow-md ring-1 ring-slate-200">
                @if($teacher->photo)
                    <img src="https://faculty.daffodilvarsity.edu.bd/images/teacher/{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300" />
                @else
                    <div class="w-full h-full bg-diu-accent text-white flex items-center justify-center font-display font-bold text-lg rounded-lg">
                        {{ $initials }}
                    </div>
                @endif
            </div>
        </div>

        <div class="pt-10 px-5">
            <h4 class="text-[15px] font-bold text-slate-900 tracking-tight leading-snug line-clamp-1 group-hover:text-diu-accent transition-colors">
                {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
            </h4>
            <p class="text-xs text-slate-600 font-medium truncate mt-0.5">{{ optional($teacher->designation)->name ?? 'Faculty Member' }}</p>
            <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-0.5">{{ optional($teacher->department)->name ?? 'General' }}</p>

            <div class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span class="truncate hover:text-diu-accent transition-colors font-mono">{{ $teacher->user->email ?? 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span class="font-sans">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span class="truncate font-sans leading-tight">{{ $teacher->office_room ?? 'N/A' }}</span>
                </div>
                @if($areaCount > 0)
                    <div class="flex flex-wrap items-center gap-1 pt-1">
                        @foreach($teachingAreas->take(2) as $ta)
                            <span class="bg-slate-100 text-slate-600 text-[9px] font-sans px-2 py-0.5 rounded-sm">{{ $ta->area }}</span>
                        @endforeach
                        @if($areaCount > 2)
                            <span class="bg-slate-100 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm">+{{ $areaCount - 2 }}</span>
                        @endif
                    </div>
                @endif
            </div>

            @if(count($interests) > 0)
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach(array_slice($interests, 0, 2) as $interest)
                        <span class="bg-diu-accent/10 text-diu-accent text-[9px] font-sans px-2 py-0.5 rounded-sm font-medium">{{ $interest }}</span>
                    @endforeach
                    @if(count($interests) > 2)
                        <span class="bg-slate-100 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm">+{{ count($interests) - 2 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <a href="{{ $profileUrl }}" class="mt-4 px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between group-hover:bg-diu-accent/5 transition-colors">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <span class="text-[10px] text-slate-500 font-semibold uppercase font-sans">{{ $teacher->publications->count() }} Publications</span>
        </div>
        <span class="text-xs font-bold text-diu-accent group-hover:text-diu-accent-hover flex items-center gap-1 transition-all">
            Profile
            <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </span>
    </a>
</div>
@else
<div class="group flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-diu-primary/30 transition-all duration-300 overflow-hidden">
    <div>
        <div class="relative h-20 bg-gradient-to-r from-diu-primary to-diu-primary-hover">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 16px 16px;"></div>
            <div class="absolute -bottom-8 left-5 w-16 h-16 rounded-xl overflow-hidden bg-white p-1 shadow-md ring-1 ring-slate-200">
                @if($teacher->photo)
                    <img src="https://faculty.daffodilvarsity.edu.bd/images/teacher/{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300" />
                @else
                    <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-lg rounded-lg">
                        {{ $initials }}
                    </div>
                @endif
            </div>
        </div>

        <div class="pt-10 px-5">
            <h4 class="text-[15px] font-bold text-slate-900 tracking-tight leading-snug line-clamp-1 group-hover:text-diu-primary transition-colors">
                {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
            </h4>
            <p class="text-xs text-slate-600 font-medium truncate mt-0.5">{{ optional($teacher->designation)->name ?? 'Faculty Member' }}</p>
            <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-0.5">{{ optional($teacher->department)->name ?? 'General' }}</p>

            <div class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span class="truncate hover:text-diu-primary transition-colors font-mono">{{ $teacher->user->email ?? 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span class="font-sans">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <svg class="w-3.5 h-3.5 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span class="truncate font-sans leading-tight">{{ $teacher->office_room ?? 'N/A' }}</span>
                </div>
                @if($areaCount > 0)
                    <div class="flex flex-wrap items-center gap-1 pt-1">
                        @foreach($teachingAreas->take(2) as $ta)
                            <span class="bg-slate-100 text-slate-600 text-[9px] font-sans px-2 py-0.5 rounded-sm">{{ $ta->area }}</span>
                        @endforeach
                        @if($areaCount > 2)
                            <span class="bg-slate-100 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm">+{{ $areaCount - 2 }}</span>
                        @endif
                    </div>
                @endif
            </div>

            @if(count($interests) > 0)
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach(array_slice($interests, 0, 2) as $interest)
                        <span class="bg-diu-primary/10 text-diu-primary text-[9px] font-sans px-2 py-0.5 rounded-sm font-medium">{{ $interest }}</span>
                    @endforeach
                    @if(count($interests) > 2)
                        <span class="bg-slate-100 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm">+{{ count($interests) - 2 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <a href="{{ $profileUrl }}" class="mt-4 px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between group-hover:bg-diu-primary/5 transition-colors">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <span class="text-[10px] text-slate-500 font-semibold uppercase font-sans">{{ $teacher->publications->count() }} Publications</span>
        </div>
        <span class="text-xs font-bold text-diu-primary group-hover:text-diu-primary-hover flex items-center gap-1 transition-all">
            Profile
            <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </span>
    </a>
</div>
@endif
