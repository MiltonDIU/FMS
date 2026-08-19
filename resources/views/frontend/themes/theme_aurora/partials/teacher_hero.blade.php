@php
    /*
     * Who this page is about.
     *
     * Two shapes, one file, because a publication page that showed a different
     * version of a person than their profile does would be two claims about
     * the same human being drifting apart.
     *
     * variant = 'full'    — the head of a profile: portrait, name, the two
     *                       things you can take away (contact card, CV), and
     *                       a short numeric summary of what follows.
     *
     * variant = 'compact' — a byline strip above a paper, so a citation read
     *                       on its own does not lose whose work it is.
     *
     * Expects $teacher, $faculty, $department. $photoUrl is computed here so a
     * caller cannot forget it.
     *
     * Both accessors go through Media::getAvailableUrl, which is the whole
     * reason they are accessors: getFirstMediaUrl('avatar', 'profile') builds
     * that address whether or not the conversion was ever generated, and only
     * 18 of the 1,861 photographs on file have been through the current ones.
     * Naming a conversion from a view answered the other 1,843 profiles with a
     * broken image.
     */

    $variant = $variant ?? 'full';

    $photoUrl = $variant === 'compact'
        ? $teacher->photo_thumb_url
        : $teacher->photo_url;

    $facSlug = $faculty?->short_name
        ? strtolower($faculty->short_name)
        : null;

    $deptSlug = $department?->code
        ? strtolower($department->code)
        : null;

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

    <div class="glass rounded-2xl p-4 flex items-center gap-4">

        <div class="mugshot" style="width: 3.75rem;">
            @if($photoUrl)
                <img
                    src="{{ $photoUrl }}"
                    alt="{{ $teacher->full_name }}"
                    loading="lazy"
                >
            @else
                <span aria-hidden="true">
                    {{ $teacher->initials }}
                </span>
            @endif
        </div>

        <div class="min-w-0">

            <p class="eyebrow-quiet">
                Author
            </p>

            <p
                class="font-display text-[16px] font-bold leading-snug mt-0.5"
                style="color: var(--ink);"
            >
                @if($profileUrl)
                    <a
                        href="{{ $profileUrl }}"
                        wire:navigate
                        class="hover:underline"
                    >
                        {{ $teacher->full_name }}
                    </a>
                @else
                    {{ $teacher->full_name }}
                @endif
            </p>

            <p
                class="text-[13px] mt-0.5"
                style="color: var(--ink-3);"
            >
                {{ optional($teacher->designation)->name ?? 'Faculty Member' }}

                @if($department?->name)
                    <span style="color: var(--hairline-strong);">
                        ·
                    </span>

                    {{ $department->name }}
                @endif
            </p>

        </div>

    </div>

@else

    {{-- Resolve administrative role only for full profile variant --}}
    @php
        $adminRoleName = optional(
            $teacher->administrativeRoles->first()
        )->administrativeRole?->name;
    @endphp


    @php
        $contact = array_filter([
            'Email' => $teacher->user->email ?? $teacher->secondary_email,
            'Phone' => $teacher->phone ?: $teacher->personal_phone,
            'Office' => $teacher->office_room,
        ], 'filled');
    @endphp


    {{-- Profile header --}}
    <div class="glass rounded-3xl p-5 sm:p-7">

        {{-- The portrait column grows at lg and not before. At the sm
             breakpoint the panel is only about 32.5rem wide inside its
             padding, so a 15rem portrait would leave the name under 16rem and
             break display-lg across three lines. --}}
        <div
            class="grid gap-6 sm:gap-8 sm:grid-cols-[11.5rem_minmax(0,1fr)]
            lg:grid-cols-[15rem_minmax(0,1fr)]
            {{ $contact ? 'xl:grid-cols-[15rem_minmax(0,1fr)_17rem]' : '' }}"
        >

            {{-- =========================================================
                 Teacher Portrait
                 ========================================================= --}}
            <div class="tile tile-plain tile-portrait justify-self-center sm:justify-self-start">

                @if($photoUrl)

                    <img
                        src="{{ $photoUrl }}"
                        alt="{{ $teacher->full_name }}"
                        loading="eager"
                    >

                @else

                    <span
                        class="tile-initials"
                        aria-hidden="true"
                    >
                        {{ $teacher->initials }}
                    </span>

                @endif

            </div>


            {{-- =========================================================
                 Teacher Identity
                 ========================================================= --}}
            <div class="min-w-0">

                @if($adminRoleName)

                    <p class="eyebrow mb-2">
                        {{ $adminRoleName }}
                    </p>

                @endif


                <h1 class="display-lg">
                    {{ $teacher->full_name }}
                </h1>


                <p
                    class="mt-2 text-[15px]"
                    style="color: var(--ink-2);"
                >
                    {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                </p>


                <p
                    class="mt-1 text-[13px]"
                    style="color: var(--ink-4);"
                >

                    @if($department?->name)

                        @if($facSlug && $deptSlug)

                            <a
                                href="{{ route('department.show', [
                                    'faculty_short_name' => $facSlug,
                                    'department_code' => $deptSlug,
                                ]) }}"
                                wire:navigate
                                class="row-link"
                            >
                                {{ $department->name }}
                            </a>

                        @else

                            {{ $department->name }}

                        @endif

                    @endif


                    @if($faculty?->name)

                        <span style="color: var(--hairline-strong);">
                            ·
                        </span>

                        <a
                            href="{{ $faculty->url }}"
                            wire:navigate
                            class="row-link"
                        >
                            {{ $faculty->name }}
                        </a>

                    @endif

                </p>


                {{-- Teacher status --}}
                <x-teacher-status
                    :teacher="$teacher"
                    variant="full"
                    class="mt-4 inline-flex"
                />


                {{-- =====================================================
                     Summary
                     ===================================================== --}}
                @php
                    $summary = array_filter([
                        'Publications' => $teacher->publications->count(),
                        'Teaching areas' => $teacher->teachingAreas->count(),
                        'Awards' => $teacher->awards->count(),
                    ]);
                @endphp


                @if($summary)

                    <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-3">

                        @foreach($summary as $label => $value)

                            <div>

                                <dt class="eyebrow-quiet">
                                    {{ $label }}
                                </dt>

                                <dd
                                    class="font-display text-xl font-extrabold mt-0.5"
                                    style="color: var(--ink);"
                                >
                                    {{ number_format($value) }}
                                </dd>

                            </div>

                        @endforeach

                    </dl>

                @endif


                {{-- =====================================================
                     Actions
                     ===================================================== --}}
                <div class="mt-6 flex flex-wrap items-center gap-2">

                    {{-- Download CV --}}
                    @if($downloadable && \App\Helpers\ProfileDownload::cvEnabled())

                        <a
                            href="{{ route('teacher.cv', [
                                'faculty_short_name' => $faculty->short_name,
                                'department_code' => $department->code,
                                'teacher_webpage' => $teacher->webpage,
                            ]) }}"
                            class="btn btn-primary"
                        >

                            <svg
                                class="w-4 h-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M12 3v12M7 12l5 5 5-5M5 21h14"/>
                            </svg>

                            Download CV

                        </a>

                    @endif


                    {{-- Save contact --}}
                    @if($downloadable && \App\Helpers\ProfileDownload::vcardEnabled())

                        <a
                            href="{{ route('teacher.vcard', [
                                'faculty_short_name' => $faculty->short_name,
                                'department_code' => $department->code,
                                'teacher_webpage' => $teacher->webpage,
                            ]) }}"
                            class="btn btn-ghost"
                        >
                            Save contact
                        </a>

                    @endif


                    {{-- Social links --}}
                    @if($teacher->socialLinks->isNotEmpty())

                        <div
                            class="flex flex-wrap items-center gap-0.5 sm:ml-2"
                        >

                            @foreach($teacher->socialLinks as $link)

                                <a
                                    href="{{ $link->url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn-icon"
                                    title="{{ optional($link->platform)->name ?? 'Link' }}"
                                >

                                    @include(
                                        'frontend.themes.theme_aurora.partials.social_icon',
                                        [
                                            'platform' => optional(
                                                $link->platform
                                            )->name ?? '',
                                        ]
                                    )

                                </a>

                            @endforeach

                        </div>

                    @endif

                </div>

            </div>


            {{-- =========================================================
                 Contact Information
                 ========================================================= --}}
            @if($contact)

                <dl
                    class="min-w-0 xl:border-l xl:pl-7"
                    style="border-color: var(--hairline-soft);"
                >

                    @foreach($contact as $label => $value)

                        <div
                            class="pair"
                            style="
                                grid-template-columns:
                                4.25rem minmax(0, 1fr);
                            "
                        >

                            <dt>
                                {{ $label }}
                            </dt>

                            <dd>

                                @if($label === 'Email')

                                    <a
                                        href="mailto:{{ $value }}"
                                        class="link-brand font-mono text-[12px]"
                                    >
                                        {{ $value }}
                                    </a>

                                @else

                                    {{ $value }}

                                @endif

                            </dd>

                        </div>

                    @endforeach

                </dl>

            @endif

        </div>

    </div>

@endif
