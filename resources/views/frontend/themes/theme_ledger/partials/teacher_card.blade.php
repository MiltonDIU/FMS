@php
    /*
     * One person in the directory — one ruled row.
     *
     * The file keeps the name every theme uses for this (Theme::REQUIRED names
     * it, and the parallel between themes is worth more than an accurate
     * filename), but nothing here is a card. A card has to repeat the
     * department and the designation as labelled lines inside its own box; a
     * row puts them in columns shared with the four hundred rows above it, so
     * they can be read down instead of across and cost no height at all.
     *
     * What the row carries, and why:
     *
     *   the photograph  — 36px square, enough to recognise someone you have
     *                     met, not enough to spend the page on;
     *   the name        — the thing being looked for;
     *   the designation — what most people are actually filtering by;
     *   the department  — the code, upper-cased, because it is an identifier;
     *   publications    — the one number this institution keeps score with.
     *
     * Below 64rem the last three fold into a single meta line under the name.
     * They are not dropped: a phone still shows what a card would have shown,
     * in two lines rather than a tile.
     *
     * Expects $teacher, and $faculty / $department for the URL. $showAdminRole
     * is passed false by the general-faculty list, where the roles have already
     * been shown in their own band above.
     */
    $pageDeptId = $department?->id;
    $pageFacId = $faculty?->id;

    $showAdminRole = $showAdminRole ?? true;

    /*
     * A dean who also heads a department holds more than one role, so the one
     * shown is the one belonging to the page being looked at rather than
     * whichever the database returned first.
     *
     * Three tries, and the third matters: an assignment naming neither a
     * faculty nor a department is still a role held, and there are a number of
     * those on file — heads and deans who would otherwise stand in the
     * administration band with nothing on them to say why. It is tried last, so
     * a more specific role wins wherever there is one.
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
     * is no picture, so the initials get their turn instead of an <img> pointed
     * at the media library's fallback path.
     */
    $photoUrl = $teacher->photo_url;

    $designation = $teacher->designation?->name;
    $deptCode = $department?->code ? strtoupper($department->code) : null;

    // The fold line, for screens with no columns to put these in.
    $fold = implode(' · ', array_filter([$designation, $deptCode ?: $department?->name], 'filled'));

    // withCount('publications') on the listing queries; the relation is the
    // fallback for anywhere this partial is reused without it.
    $publications = $teacher->publications_count ?? $teacher->publications->count();
@endphp

<a @if($profileUrl) href="{{ $profileUrl }}" wire:navigate @endif
   class="lrow"
   aria-label="{{ $teacher->full_name }}{{ $designation ? ', ' . $designation : '' }}">

    <span class="lrow-no" aria-hidden="true"></span>

    <span class="lrow-face">
        @if($photoUrl)
            <img src="{{ $photoUrl }}" alt="" loading="lazy" decoding="async">
        @else
            <span aria-hidden="true">{{ $teacher->initials ?: '—' }}</span>
        @endif
    </span>

    <span class="min-w-0">
        <span class="lrow-line">
            <span class="lrow-name">{{ $teacher->full_name }}</span>

            @if($adminRoleName)
                <span class="stamp">{{ $adminRoleName }}</span>
            @endif

            {{-- Says so when this person is not currently at their desk — on
                 leave, on deputation. Silent for anyone working normally, so it
                 only appears when it carries information. --}}
            <x-teacher-status :teacher="$teacher" class="ml-2 shrink-0" />
        </span>

        @if($fold)
            <span class="lrow-fold">{{ $fold }}</span>
        @endif
    </span>

    <span class="lrow-role">{{ $designation }}</span>

    <span class="lrow-dept">{{ $deptCode }}</span>

    <span class="lrow-pubs" title="{{ $publications }} recorded publications">{{ $publications }}</span>
</a>
