@php
    /*
     * Who a page is about.
     *
     * Three shapes, one file, because a publication page that showed a
     * different version of a person than their profile does would be two claims
     * about the same human being drifting apart.
     *
     *   variant = 'head'    — the top of a profile: the name set large across
     *                         the full measure, with the post under it and a
     *                         rule closing it. Nothing else, so the name is the
     *                         first thing read on the page.
     *
     *   variant = 'stub'    — the left column of the profile sheet: portrait,
     *                         contact block, the two things you can take away,
     *                         and the scholarly profile links.
     *
     *   variant = 'compact' — a byline strip above a paper, so a citation read
     *                         on its own does not lose whose work it is.
     *
     * Expects $teacher, $faculty, $department. The photograph is resolved here
     * so a caller cannot forget it.
     *
     * Both photo accessors go through Media::getAvailableUrl, which is the whole
     * reason they are accessors: getFirstMediaUrl('avatar', 'profile') builds
     * that address whether or not the conversion was ever generated, and only a
     * handful of the photographs on file have been through the current ones.
     * Naming a conversion from a view answered the rest with a broken image.
     */
    $variant = $variant ?? 'head';

    $photoUrl = $variant === 'compact'
        ? $teacher->photo_thumb_url
        : $teacher->photo_url;

    $facSlug = $faculty?->short_name ? strtolower($faculty->short_name) : null;
    $deptSlug = $department?->code ? strtolower($department->code) : null;

    $profileUrl = ($facSlug && $deptSlug && $teacher->webpage)
        ? route('teacher.show', [
            'faculty_short_name' => $facSlug,
            'department_code' => $deptSlug,
            'teacher_webpage' => $teacher->webpage,
        ])
        : null;

    $downloadable = $facSlug && $deptSlug && $teacher->webpage;
@endphp

@if($variant === 'compact')

    {{-- ── Byline strip ──────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 py-4 rule">
        <span class="mugshot">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="" loading="lazy">
            @else
                <span aria-hidden="true">{{ $teacher->initials }}</span>
            @endif
        </span>

        <div class="min-w-0">
            <p class="label">Author</p>

            <p class="title-md mt-1">
                @if($profileUrl)
                    <a href="{{ $profileUrl }}" wire:navigate class="hover:underline">{{ $teacher->full_name }}</a>
                @else
                    {{ $teacher->full_name }}
                @endif
            </p>

            <p class="text-[13px] mt-0.5" style="color: var(--ink-3);">
                {{ $teacher->designation?->name ?? 'Faculty Member' }}@if($department?->name), {{ $department->name }}@endif
            </p>
        </div>
    </div>

@elseif($variant === 'stub')

    @php
        /*
         * The facts a directory entry exists to carry. Built first and rendered
         * only if it has any, so a profile with nothing recorded shows no empty
         * table rather than four labels against blanks.
         *
         * The employee number is here and nowhere else on the site. It is the
         * key the university's own systems use for a person, it is already
         * searchable in the finder above, and this is a ledger — an entry with
         * no reference number is half an entry.
         */
        $contact = array_filter([
            'Email' => $teacher->user->email ?? $teacher->secondary_email,
            'Phone' => $teacher->phone ?: $teacher->personal_phone,
            'Office' => $teacher->office_room,
            'ID' => $teacher->employee_id,
        ], 'filled');
    @endphp

    <div class="space-y-6">

        <div class="portrait">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $teacher->full_name }}" loading="eager">
            @else
                <span aria-hidden="true">{{ $teacher->initials }}</span>
            @endif
        </div>

        <x-teacher-status :teacher="$teacher" variant="full" class="inline-flex" />

        @if($contact)
            <dl>
                <p class="label pb-1.5 rule-hard" style="border-top: 0; border-bottom: 1px solid var(--rule-strong);">Contact</p>
                @foreach($contact as $label => $value)
                    <div class="pair" style="grid-template-columns: 3.75rem minmax(0, 1fr);">
                        <dt>{{ $label }}</dt>
                        <dd>
                            @if($label === 'Email')
                                <a href="mailto:{{ $value }}" class="link font-mono text-[11.5px]">{{ $value }}</a>
                            @elseif($label === 'ID')
                                <span class="font-mono text-[11.5px]">{{ $value }}</span>
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if($downloadable && (\App\Helpers\ProfileDownload::cvEnabled() || \App\Helpers\ProfileDownload::vcardEnabled()))
            <div class="flex flex-wrap gap-2">
                @if(\App\Helpers\ProfileDownload::cvEnabled())
                    <a href="{{ route('teacher.cv', [
                            'faculty_short_name' => $faculty->short_name,
                            'department_code' => $department->code,
                            'teacher_webpage' => $teacher->webpage,
                       ]) }}" class="btn btn-solid">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12M7 12l5 5 5-5M5 21h14"/>
                        </svg>
                        Download CV
                    </a>
                @endif

                @if(\App\Helpers\ProfileDownload::vcardEnabled())
                    <a href="{{ route('teacher.vcard', [
                            'faculty_short_name' => $faculty->short_name,
                            'department_code' => $department->code,
                            'teacher_webpage' => $teacher->webpage,
                       ]) }}" class="btn">Save contact</a>
                @endif
            </div>
        @endif

        @if($teacher->socialLinks->isNotEmpty())
            <div>
                <p class="label pb-1.5" style="border-bottom: 1px solid var(--rule-strong);">Profiles</p>
                <div class="mt-2 flex flex-wrap items-center gap-0.5">
                    @foreach($teacher->socialLinks as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                           class="btn-icon" title="{{ $link->platform?->name ?? 'Link' }}">
                            @include('frontend.themes.theme_ledger.partials.social_icon', [
                                'platform' => $link->platform?->name ?? '',
                            ])
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

@else

    {{-- ── The head of a profile ─────────────────────────────────────────── --}}
    @php
        $adminRoleName = $teacher->administrativeRoles->first()?->administrativeRole?->name;
    @endphp

    <div class="pb-5 rule-double-b">
        @if($adminRoleName)
            <p class="label label-ink mb-3">{{ $adminRoleName }}</p>
        @endif

        <h1 class="title-xl">{{ $teacher->full_name }}</h1>

        <p class="mt-3 text-[15px]" style="color: var(--ink-2);">
            {{ $teacher->designation?->name ?? 'Faculty Member' }}
        </p>

        <p class="mt-1 text-[13px]" style="color: var(--ink-4);">
            @if($department?->name)
                @if($facSlug && $deptSlug)
                    <a href="{{ route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => $deptSlug]) }}"
                       wire:navigate class="hover:underline">{{ $department->name }}</a>
                @else
                    {{ $department->name }}
                @endif
            @endif

            @if($faculty?->name)
                <span aria-hidden="true"> · </span>
                <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->name }}</a>
            @endif
        </p>
    </div>

@endif
