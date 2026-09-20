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
        $primaryEmail = $teacher->user?->email;
        $secondaryEmail = ($teacher->secondary_email && $teacher->secondary_email !== $primaryEmail) ? $teacher->secondary_email : null;

        $contact = [];
        if ($primaryEmail) {
            $contact[] = [
                'type' => 'email',
                'label' => 'Primary Email',
                'value' => $primaryEmail,
            ];
        }
        if ($secondaryEmail) {
            $contact[] = [
                'type' => 'email',
                'label' => 'Secondary Email',
                'value' => $secondaryEmail,
            ];
        }
        if ($phone = ($teacher->phone ?: $teacher->personal_phone)) {
            $contact[] = [
                'type' => 'phone',
                'label' => 'Phone',
                'value' => $phone,
            ];
        }
        if ($teacher->office_room) {
            $contact[] = [
                'type' => 'office',
                'label' => 'Office',
                'value' => $teacher->office_room,
            ];
        }
    @endphp


    {{-- Profile header --}}
    <div class="glass rounded-3xl p-5 sm:p-7">

        {{-- The portrait column grows at lg and not before. At the sm
             breakpoint the panel is only about 32.5rem wide inside its
             padding, so a 15rem portrait would leave the name under 16rem and
             break display-lg across three lines. --}}
        <div
            class="grid gap-6 sm:gap-8 sm:grid-cols-[11.5rem_minmax(0,1fr)]
            {{ $contact ? 'lg:grid-cols-[12rem_minmax(0,1fr)_minmax(16rem,auto)]' : 'lg:grid-cols-[12rem_minmax(0,1fr)]' }}"
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

                {{-- Spans both columns until the grid grows a third one.

                     Without this it is a third child in a two-column grid, so
                     auto-placement puts it in row 2 of the *portrait* column:
                     104px wide for an email address, under a hole as tall as
                     the identity column beside it. The border only appears at
                     xl for the same reason — that is the first width at which
                     this is a column of its own rather than a footer to the
                     panel. --}}
                <dl
                    class="min-w-0 sm:col-span-2 lg:col-span-1 lg:border-l lg:pl-6"
                    style="border-color: var(--hairline-soft);"
                >

                    @foreach($contact as $item)

                        <div
                            class="pair"
                            style="
                                grid-template-columns: 1.25rem minmax(0, 1fr);
                                gap: 0.5rem;
                                align-items: center;
                            "
                        >

                            <dt class="flex items-center justify-start" style="color: var(--ink-4);" title="{{ $item['label'] }}">
                                @if($item['type'] === 'email')
                                    <svg class="w-4 h-4 shrink-0 text-current" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect width="20" height="16" x="2" y="4" rx="2"/>
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                    </svg>
                                    <span class="sr-only">{{ $item['label'] }}</span>
                                @elseif($item['type'] === 'phone')
                                    <svg class="w-4 h-4 shrink-0 text-current" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                    <span class="sr-only">{{ $item['label'] }}</span>
                                @elseif($item['type'] === 'office')
                                    <svg class="w-4 h-4 shrink-0 text-current" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <span class="sr-only">{{ $item['label'] }}</span>
                                @else
                                    {{ $item['label'] }}
                                @endif
                            </dt>

                            <dd class="min-w-0" style="overflow-wrap: normal; word-break: normal; white-space: nowrap;">

                                @if($item['type'] === 'email')

                                    <a
                                        href="mailto:{{ $item['value'] }}"
                                        class="link-brand font-mono text-[12px] block hover:underline"
                                        style="white-space: nowrap; overflow-wrap: normal; word-break: normal;"
                                        title="{{ $item['value'] }}"
                                    >
                                        {{ $item['value'] }}
                                    </a>

                                @else

                                    <span style="white-space: nowrap;">{{ $item['value'] }}</span>

                                @endif

                            </dd>

                        </div>

                    @endforeach

                </dl>

            @endif

        </div>

    </div>

@endif
