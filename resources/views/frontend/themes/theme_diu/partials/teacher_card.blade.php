@php
    $pageDeptId = $department?->id;
    $pageFacId = $faculty?->id;

    $showAdminRole = $showAdminRole ?? true;
    $adminRole = null;
    // The role belonging to the page being looked at, then one held across the
    // faculty, then whatever they hold. The last try is the fix: $pageFacId is
    // always set here, so the old else could never run, and an assignment
    // naming neither a faculty nor a department — nine of the forty-one on file
    // — matched nothing. Those teachers appeared in Administration with no
    // badge and, here, without the admin styling either.
    $roles = $teacher->administrativeRoles;

    $adminRole = ($pageDeptId ? $roles->firstWhere('department_id', $pageDeptId) : null)
        ?: ($pageFacId ? $roles->first(fn ($r) => $r->department_id === null && (int) $r->faculty_id === (int) $pageFacId) : null)
        ?: $roles->first();
    $isAdmin = $showAdminRole && ! is_null($adminRole);
    $adminRoleName = $adminRole?->administrativeRole?->name;

    // Where the role is held. "Head" alone does not say of what, and on the
    // directory's front page a dean and a department head sit side by side.
    $adminRoleScope = null;
    if ($isAdmin) {
        if ($adminRole->faculty) {
            $adminRoleScope = $adminRole->faculty->short_name ?: $adminRole->faculty->name;
        } elseif ($adminRole->department) {
            $adminRoleScope = $adminRole->department->code ?: $adminRole->department->short_name ?: $adminRole->department->name;
        }
    }
    $adminRoleBadge = $adminRoleName
        ? ($adminRoleScope ? "{$adminRoleName}, {$adminRoleScope}" : $adminRoleName)
        : null;

    $initials = $teacher->initials;
    $interests = $teacher->researchInterestNames();
    $teachingAreas = $teacher->teachingAreas;
    $areaCount = $teachingAreas->count();

    // Only the facts this person actually has. A card of "N/A" rows says less
    // than a card without them, and says it three times.
    $email = $teacher->user?->email;
    $phone = $teacher->phone ?: $teacher->personal_phone;
    $office = $teacher->office_room;

    // Null rather than '#': faculties.short_name, departments.code and
    // teachers.webpage are all nullable, and a card that links to the top of
    // the page it is already on is a dead end that looks like a link.
    $profileUrl = ($faculty?->short_name && $department?->code && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ])
        : null;

    /*
     * The two cards differ only in tone: the administration card is drawn in
     * the accent colour and carries the role badge, everyone else is drawn in
     * the primary. Whole class strings rather than a colour name spliced into
     * them, so Tailwind's scanner can still see every one.
     */
    $tone = $isAdmin
        ? [
            'card' => 'hover:border-diu-accent/30',
            'banner' => 'from-diu-accent to-diu-accent-hover',
            'initials' => 'bg-diu-accent',
            'name' => 'group-hover:text-diu-accent',
            'icon' => 'text-diu-accent',
            'link' => 'hover:text-diu-accent',
            'interest' => 'bg-diu-accent/10 text-diu-accent',
            'foot' => 'group-hover:bg-diu-accent/5',
            'cta' => 'text-diu-accent group-hover:text-diu-accent-hover',
        ]
        : [
            'card' => 'hover:border-diu-primary/30',
            'banner' => 'from-diu-primary to-diu-primary-hover',
            'initials' => 'bg-diu-primary',
            'name' => 'group-hover:text-diu-primary',
            'icon' => 'text-diu-primary',
            'link' => 'hover:text-diu-primary',
            'interest' => 'bg-diu-primary/10 text-diu-primary',
            'foot' => 'group-hover:bg-diu-primary/5',
            'cta' => 'text-diu-primary group-hover:text-diu-primary-hover',
        ];
@endphp

<div class="group flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl {{ $tone['card'] }} transition-all duration-300 overflow-hidden">
    <div class="flex-1">
        <div class="relative h-20 bg-gradient-to-r {{ $tone['banner'] }}">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 16px 16px;"></div>

            <div class="absolute left-5 flex items-center gap-3" style="bottom: -24px;">
                <div class="w-20 h-20 rounded-xl overflow-hidden bg-white p-1 shadow-md ring-1 ring-slate-200 shrink-0">
                    @if($teacher->photo_url)
                        <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->full_name }}" loading="lazy" decoding="async"
                             class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300" />
                    @else
                        <div class="w-full h-full {{ $tone['initials'] }} text-white flex items-center justify-center font-display font-bold text-lg rounded-lg">
                            {{ $initials }}
                        </div>
                    @endif
                </div>

                @if($isAdmin && $adminRoleBadge)
                    <div class="inline-flex items-center gap-1 bg-white/20 text-white text-[9px] font-sans font-bold uppercase px-2 py-0.5 rounded-sm backdrop-blur-sm">
                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                        {{ $adminRoleBadge }}
                    </div>
                @endif
            </div>
        </div>

        <div class="pt-10 px-5">
            <h4 class="text-[15px] font-bold text-slate-900 tracking-tight leading-snug line-clamp-1 {{ $tone['name'] }} transition-colors">
                @if($profileUrl)
                    <a href="{{ $profileUrl }}" wire:navigate>{{ $teacher->full_name }}</a>
                @else
                    {{ $teacher->full_name }}
                @endif
            </h4>
            <p class="text-xs text-slate-600 font-medium truncate mt-0.5">{{ $teacher->designation_title ?? 'Faculty Member' }}</p>
            <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-0.5">{{ optional($teacher->department)->name ?? 'General' }}</p>
            <x-teacher-engagement :teacher="$teacher" class="mt-1.5" />
            <x-teacher-status :teacher="$teacher" class="mt-1.5" />

            @if($email || $phone || $office || $areaCount > 0)
                <div class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                    @if($email)
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 {{ $tone['icon'] }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <a href="mailto:{{ $email }}" class="truncate {{ $tone['link'] }} transition-colors font-mono">{{ $email }}</a>
                        </div>
                    @endif
                    @if($phone)
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 {{ $tone['icon'] }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span class="font-sans">{{ $phone }}</span>
                        </div>
                    @endif
                    @if($office)
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 {{ $tone['icon'] }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="truncate font-sans leading-tight">{{ $office }}</span>
                        </div>
                    @endif
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
            @endif

            @if(count($interests) > 0)
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach(array_slice($interests, 0, 2) as $interest)
                        <span class="{{ $tone['interest'] }} text-[9px] font-sans px-2 py-0.5 rounded-sm font-medium">{{ $interest }}</span>
                    @endforeach
                    @if(count($interests) > 2)
                        <span class="bg-slate-100 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm">+{{ count($interests) - 2 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <a @if($profileUrl) href="{{ $profileUrl }}" wire:navigate @endif
       class="mt-4 px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between {{ $tone['foot'] }} transition-colors"
       aria-label="{{ $teacher->full_name }}{{ $teacher->designation_title ? ', ' . $teacher->designation_title : '' }}">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 {{ $tone['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <span class="text-[10px] text-slate-500 font-semibold uppercase font-sans">{{ $teacher->publications_count ?? $teacher->publications->count() }} Publications</span>
        </div>
        @if($profileUrl)
            <span class="text-xs font-bold {{ $tone['cta'] }} flex items-center gap-1 transition-all">
                Profile
                <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </span>
        @endif
    </a>
</div>
