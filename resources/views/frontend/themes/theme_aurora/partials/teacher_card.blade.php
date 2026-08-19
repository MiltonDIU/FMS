@php
    /*
     * One person in the directory.
     *
     * The photograph is the card. Every other theme sets a 40–56px disc beside
     * two or three lines of text, which crops the top and sides off every face
     * and still leaves the text doing all the recognising. Here the picture
     * fills a 4:5 tile and the name sits on a glass plate across the bottom of
     * it, so a page of results reads as a wall of faces — which is how anyone
     * scanning a faculty of two thousand actually finds someone they have met.
     *
     * Only name, designation and department appear. Email, teaching areas and
     * publication counts live on the profile; a tile carrying everything stops
     * being scannable, which is the one thing a directory has to be.
     *
     * Expects $teacher, and $faculty / $department for the URL. $showAdminRole
     * is passed false by the general-faculty list, where the roles have already
     * been shown in their own group above.
     */
    $pageDeptId = $department?->id;
    $pageFacId = $faculty?->id;

    $showAdminRole = $showAdminRole ?? true;
    $adminRole = null;

    /*
     * A dean who also heads a department holds more than one role, so the one
     * shown is the one belonging to the page being looked at rather than
     * whichever the database returned first.
     *
     * Three tries rather than two branches, and the third is the point. This
     * was an if / elseif / else whose else could never run: the card is handed
     * the teacher's own faculty, so $pageFacId is always set and the fallback
     * was unreachable. An assignment naming neither a faculty nor a department
     * then matched neither test and the badge simply vanished — while the same
     * person's profile page, which takes the first role and asks no questions,
     * said "Head of Department" plainly. Nine of the forty-one assignments on
     * file are that shape: seven heads, a dean and an associate dean, standing
     * in the Administration group with nothing on them to say why.
     *
     * A role that names no faculty and no department is still a role held. It
     * is tried last, so a more specific one wins wherever there is one.
     */
    $roles = $teacher->administrativeRoles;

    $adminRole = ($pageDeptId ? $roles->firstWhere('department_id', $pageDeptId) : null)
        ?: ($pageFacId ? $roles->first(fn ($r) => $r->department_id === null && (int) $r->faculty_id === (int) $pageFacId) : null)
        ?: $roles->first();

    $adminRoleName = ($showAdminRole && $adminRole) ? $adminRole->administrativeRole?->name : null;

    $profileUrl = ($faculty?->short_name && $department?->code && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => strtolower($faculty->short_name),
            'department_code' => strtolower($department->code),
            'teacher_webpage' => $teacher->webpage,
        ])
        : null;

    /*
     * photo_url answers this from our own storage and returns null when there
     * is no picture, so the initials panel below gets its turn instead of an
     * <img> pointed at the media library's fallback path.
     */
    $photoUrl = $teacher->photo_url;
@endphp

<a @if($profileUrl) href="{{ $profileUrl }}" wire:navigate @endif
   class="tile group"
   aria-label="{{ $teacher->full_name }}{{ $teacher->designation?->name ? ', ' . $teacher->designation->name : '' }}">

    @if($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $teacher->full_name }}" loading="lazy" decoding="async">
    @else
        <span class="tile-initials" aria-hidden="true">{{ $teacher->initials ?: '—' }}</span>
    @endif

    @if($adminRoleName)
        <span class="tile-badge">{{ $adminRoleName }}</span>
    @endif

    {{-- Says so when this person is not currently at their desk — on leave, on
         deputation. Silent for anyone working normally, so it only appears when
         it carries information. --}}
    <x-teacher-status :teacher="$teacher" class="tile-status" />

    <span class="tile-plate">
        <span class="tile-name block">{{ $teacher->full_name }}</span>

        @if($teacher->designation?->name)
            <span class="tile-meta block">{{ $teacher->designation->name }}</span>
        @endif

        @if($department?->code || $department?->name)
            <span class="tile-meta block" style="opacity: 0.7; letter-spacing: 0.06em; text-transform: uppercase; font-size: 0.6875rem;">
                {{ $department->code ?: $department->name }}
            </span>
        @endif
    </span>
</a>
