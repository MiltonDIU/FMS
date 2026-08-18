@php
    /*
     * Portrait-forward directory card.
     *
     * The other themes set a small circular avatar beside the text; here the
     * photograph is the card and the text sits beneath it. People scanning a
     * faculty of two thousand recognise faces long before they read names, so
     * the picture earns the space.
     *
     * Only name, designation and department appear. Email and teaching areas
     * live on the profile page — at this size a card carrying everything stops
     * being scannable, which is the one thing a directory has to be.
     */
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

    $adminRoleName = ($showAdminRole && $adminRole) ? $adminRole->administrativeRole?->name : null;

    $initials = $teacher->initials;

    $profileUrl = ($faculty?->short_name && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ])
        : '#';

    /*
     * photo_url answers this once, from our own storage, and returns null when
     * there is no picture so the initials panel below gets its turn. This used
     * to build the address here against the external faculty host.
     */
    $photoUrl = $teacher->photo_url;
@endphp

<a href="{{ $profileUrl }}" wire:navigate
   class="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-diu-primary focus-visible:ring-offset-2">

    <article class="flex flex-col">

        {{-- The photograph. Framed rather than cropped to a circle, so the whole
             face survives however the original was shot. --}}
        <div class="portrait-frame relative rounded-xl ring-1 ring-slate-200/80 shadow-sm
                    transition-all duration-300 group-hover:shadow-lg group-hover:ring-diu-primary/30">

            @if($photoUrl)
                <img src="{{ $photoUrl }}"
                     alt="{{ $teacher->full_name }}"
                     loading="lazy"
                     class="transition-transform duration-500 group-hover:scale-[1.03]">
            @else
                <div class="portrait-fallback text-3xl">{{ $initials ?: '—' }}</div>
            @endif

            @if($adminRoleName)
                {{-- Sits on the image because the role belongs to this person,
                     not to a separate line of facts underneath. --}}
                <span class="absolute bottom-0 left-0 m-2 rounded-md bg-diu-primary-dark/85 px-2 py-1
                             text-[10px] font-semibold uppercase tracking-wider text-white backdrop-blur-sm">
                    {{ $adminRoleName }}
                </span>
            @endif
        </div>

        <div class="pt-3">
            <h3 class="font-display text-[15px] font-semibold leading-snug text-slate-900
                       transition-colors group-hover:text-diu-primary">
                {{ $teacher->full_name }}
            </h3>

            @if($teacher->designation?->name)
                <p class="mt-0.5 text-[13px] leading-snug text-slate-600">
                    {{ $teacher->designation->name }}
                </p>
            @endif

            @if($department?->code || $department?->name)
                <p class="mt-1 text-[11px] uppercase tracking-wider text-slate-400">
                    {{ $department->code ?: $department->name }}
                </p>
            @endif

            <x-teacher-status :teacher="$teacher" class="mt-2" />
        </div>

    </article>
</a>
