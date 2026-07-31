@php
    /*
     * The teacher's identity column, shared by the profile page and the
     * publication page.
     *
     * Reading a publication without seeing whose it is loses the thread, so both
     * pages carry the same sticky column: portrait, name, how to reach them.
     * Keeping one copy means they cannot drift apart.
     *
     * Expects $teacher, $faculty and $department. $photoUrl is computed here so
     * a caller cannot forget it — the raw photo attribute is never safe to put
     * straight into an <img>.
     */
    $photoUrl = $teacher->photo_url;
@endphp

    <aside class="lg:sticky lg:self-start"
       style="top: calc(var(--header-height) + 1.5rem);">

        <div class="portrait-frame rounded-lg ring-1 ring-slate-200/70 mb-6" style="width: 100%;">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $teacher->full_name }}">
            @else
                <div class="portrait-fallback text-6xl">
                    {{ $teacher->initials }}
                </div>
            @endif
        </div>

        @php
            $adminRoleName = optional($teacher->administrativeRoles->first())->administrativeRole?->name;
        @endphp

        @if($adminRoleName)
            <p class="eyebrow mb-2">{{ $adminRoleName }}</p>
        @endif

        <h1 class="font-display text-2xl font-extrabold leading-tight tracking-tight"
            style="color: var(--text-strong);">
            {{ $teacher->full_name }}
        </h1>

        <p class="mt-1.5 text-[15px]" style="color: var(--text-soft);">
            {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
        </p>
        <p class="mt-0.5 text-[13px]" style="color: var(--text-muted);">
            {{ optional($teacher->department)->name }}
        </p>

        {{-- Says so when this person is on leave, so the page does not imply
             they are at their desk. Silent for anyone working normally. --}}
        <x-teacher-status :teacher="$teacher" variant="full" class="mt-4" />

        {{-- Contact as a definition list, not a row of icon tiles. --}}
        <dl class="mt-7 space-y-0 text-[13px]">
            @foreach([
                ['Email', $teacher->user->email ?? null],
                ['Phone', $teacher->phone ?: $teacher->personal_phone],
                ['Office', $teacher->office_room],
            ] as $row)
                @if(filled($row[1]))
                    <div class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-3 border-t py-3"
                         style="border-color: var(--border-faint);">
                        <dt class="text-[11px] uppercase tracking-wider pt-0.5"
                            style="color: var(--text-muted);">{{ $row[0] }}</dt>
                        <dd class="break-words" style="color: var(--text-soft);">{{ $row[1] }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>

        @if($teacher->socialLinks->isNotEmpty())
            <div class="mt-5 flex flex-wrap items-center gap-1">
                @foreach($teacher->socialLinks as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                       class="w-8 h-8 rounded-md flex items-center justify-center transition-colors hover:bg-surface-hover"
                       style="color: var(--text-muted);"
                       title="{{ optional($link->platform)->name ?? 'Link' }}">
                        @include('frontend.themes.theme_portrait.partials.social_icon', ['platform' => optional($link->platform)->name ?? ''])
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-7 flex flex-wrap gap-2">
            @if(\App\Helpers\ProfileDownload::vcardEnabled())
                <a href="{{ route('teacher.vcard', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                   class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold rounded-md border transition-colors hover:bg-surface-hover"
                   style="border-color: var(--border-soft); color: var(--text-soft);">
                    Save contact
                </a>
            @endif
            @if(\App\Helpers\ProfileDownload::cvEnabled())
                <a href="{{ route('teacher.cv', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                   class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold rounded-md text-white"
                   style="background-color: var(--color-diu-primary);">
                    Download CV
                </a>
            @endif
        </div>
    </aside>
