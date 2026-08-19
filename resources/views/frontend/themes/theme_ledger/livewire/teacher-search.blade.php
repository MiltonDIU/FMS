{{--
    The directory as a ruled index.

    Three strips stack against the top of the window and stay there: the
    masthead, the finder, and the column header. Everything below them is rows.
    That ordering is the whole layout — what you are searching, what you are
    searching with, what the columns mean, and then four hundred people.

    Filter state lives in the Livewire component ($q, facultyId, departmentId,
    designationId, adminRoleId); only whether the refine drawer is open is
    Alpine's, and it is deliberately opened for you when a filter is already
    applied, so an active filter is never hidden behind a closed drawer.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();

    // Whether the drawer has anything in it at all. Within a single department
    // there may be no administrative roles and only one designation, and an
    // empty panel opening under the index is worse than no control.
    $hasDrawer = $this->visibleDesignations->isNotEmpty()
        || $this->visibleAdminRoles->isNotEmpty()
        || $activeFilters > 0;

    /*
     * What every faculty and department link carries with it.
     *
     * Those links are wire:navigate — a new page — so anything not in the URL
     * is gone. A bare /fbe would silently throw away the designation and the
     * role the reader had picked. Narrowing one axis should not reset the
     * others: if they want "Any" they will say so, and Any is one click away.
     *
     * The search text travels for the same reason. Typing a name and then
     * picking a faculty reads as "that name, within this faculty", not as a
     * request to start again.
     *
     * The component reads all three back in mount(), and Seo::canonicalUrl is
     * built from the route and its path parameters only — so none of this adds
     * a second address for the same page.
     */
    $carry = array_filter([
        'q' => trim($q),
        'designation' => $this->designationId,
        'admin' => $this->adminRoleId,
    ], 'filled');
@endphp

<div>

    {{-- A sticky element reports its stuck position, not its real one, so this
         empty marker holds the place the finder would occupy if it were not
         sticky. theme.js reads it to put the page back where it was after a
         keystroke changes how many rows there are. --}}
    <div data-finder-anchor aria-hidden="true"></div>

    <div class="finder"
         x-data="{
            open: false,
            init() {
                /*
                 * Faculty and department are wire:navigate links, so clicking
                 * one rebuilds this component from scratch and Alpine starts
                 * over — which slammed the drawer shut on someone who had
                 * deliberately opened it, while designation and role (a morph)
                 * left it alone. Same control, two behaviours, for a reason
                 * invisible from the outside.
                 *
                 * Remembered for the browsing session rather than forever: it
                 * is how you are working now, not a setting.
                 */
                try { this.open = sessionStorage.getItem('ledger-refine') === 'open'; } catch (e) {}

                // An applied filter is never left hidden behind a shut drawer.
                if (@js($activeFilters > 0)) this.open = true;

                this.$watch('open', (value) => {
                    try { sessionStorage.setItem('ledger-refine', value ? 'open' : 'shut'); } catch (e) {}
                });
            }
         }">

        {{-- A writing line rather than a boxed input: a rule underneath, the
             caret sitting on it. --}}
        <div class="finder-field">
            <svg class="w-4 h-4 shrink-0" style="color: var(--ink-4);" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>

            <input type="search"
                   wire:model.live.debounce.300ms="q"
                   placeholder="Search by name, employee ID, designation, department or faculty"
                   aria-label="Search the faculty directory">

            @if($q)
                <button type="button" wire:click="clearSearch" class="btn-icon shrink-0" aria-label="Clear search">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            @endif

            <span class="figure shrink-0 hidden sm:block">{{ number_format($totalResults) }}</span>

            @if($hasDrawer)
                <button type="button" class="pick shrink-0" :class="{ 'is-active': open }" @click="open = ! open"
                        aria-label="Refine the list">
                    Refine
                    @if($activeFilters)
                        <span class="font-mono">{{ $activeFilters }}</span>
                    @endif
                </button>
            @endif
        </div>

        {{-- Faculties, as an index. Real links rather than component state, so a
             faculty is an address that can be shared and the back button
             behaves. --}}
        <div class="index-rail index-scroll pt-1.5" role="list" aria-label="Faculties">
            <a href="{{ route('home', $carry) }}" wire:navigate role="listitem"
               class="index-link {{ $this->facultyId ? '' : 'is-active' }}">All</a>

            @foreach($this->faculties as $fac)
                {{-- Built here rather than through $fac->url, which takes no
                     query parameters. Same fallback as the accessor: short_name
                     is nullable, and route() would throw on a missing one. --}}
                @php
                    $facUrl = $fac->short_name
                        ? route('faculty.show', array_merge(
                            ['faculty_short_name' => strtolower($fac->short_name)],
                            $carry,
                        ))
                        : route('home', $carry);
                @endphp

                <a href="{{ $facUrl }}" wire:navigate role="listitem"
                   class="index-link {{ (string) $fac->id === (string) $this->facultyId ? 'is-active' : '' }}">
                    {{ $fac->short_name ?: $fac->name }}
                    <span class="figure">{{ $fac->teachers_count }}</span>
                </a>
            @endforeach
        </div>

        @if($this->departments->isNotEmpty() && $this->selectedFaculty)
            <div class="index-rail index-scroll" style="border-top: 1px solid var(--rule-soft);"
                 role="list" aria-label="Departments">
                @foreach($this->departments as $dept)
                    <a href="{{ route('department.show', array_merge([
                            'faculty_short_name' => strtolower($this->selectedFaculty->short_name),
                            'department_code' => strtolower($dept->code),
                       ], $carry)) }}" wire:navigate role="listitem"
                       class="index-link {{ (string) $dept->id === (string) $this->departmentId ? 'is-active' : '' }}"
                       style="font-weight: 500;">
                        {{ $dept->short_name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($hasDrawer)
            <div x-show="open" x-cloak class="finder-drawer space-y-4">

                @if($this->visibleDesignations->isNotEmpty())
                    <div>
                        <p class="label mb-2">Designation</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="setDesignation(null)"
                                    class="pick {{ $this->designationId ? '' : 'is-active' }}">Any</button>
                            @foreach($this->visibleDesignations as $desig)
                                <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                        class="pick {{ $this->designationId == $desig->id ? 'is-active' : '' }}">
                                    {{ $desig->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($this->visibleAdminRoles->isNotEmpty())
                    <div>
                        <p class="label mb-2">Administrative role</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="setAdmin(null)"
                                    class="pick {{ $this->adminRoleId ? '' : 'is-active' }}">Any</button>
                            @foreach($this->visibleAdminRoles as $role)
                                <button type="button" wire:click="setAdmin({{ $role->id }})"
                                        class="pick {{ $this->adminRoleId == $role->id ? 'is-active' : '' }}">
                                    {{ $role->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($this->facultyId || $this->departmentId || $activeFilters)
                    <a href="{{ route('home') }}" wire:navigate class="inline-block text-[12px] font-semibold link">
                        Clear everything
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- Results. Dimmed rather than emptied while Livewire is in flight: the
         rows staying put is far less jarring than the page blanking and
         refilling on every keystroke.

         The id gives the listing a stable address — #results on any directory
         URL lands on the rows rather than the top of the page. --}}
    <div id="results" class="mt-6"
         wire:loading.class="is-busy" wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 mb-4">
            <h2 class="title-lg">
                @if($q)
                    &ldquo;{{ $q }}&rdquo;
                @elseif($this->selectedFaculty)
                    {{ $this->selectedFaculty->name }}
                @else
                    Everyone at {{ \App\Helpers\Branding::get('short_name') }}
                @endif
            </h2>

            <p class="figure">
                {{ number_format($totalResults) }}
                {{ \Illuminate\Support\Str::plural('result', $totalResults) }}
                of {{ number_format($this->staticTeacherCount) }}
            </p>
        </div>

        @if($totalResults === 0)

            <div class="empty">
                <p class="title-md mb-1">Nobody matches that.</p>
                <p class="text-[14px]">Try a shorter search, or clear the filters above.</p>
            </div>

        @else

            {{-- One ledger, two bands. The administration rows come first
                 because that is what somebody arriving at a faculty usually
                 wants, and the row numbering runs straight through both — it
                 counts positions on the page, not entries in a list. --}}
            <div class="ledger">

                <div class="ledger-head">
                    <div class="lrow lrow-head">
                        <span class="lrow-no">#</span>
                        <span aria-hidden="true"></span>
                        <span>Name</span>
                        <span class="lrow-role">Designation</span>
                        <span class="lrow-dept">Dept</span>
                        <span class="lrow-pubs">Pub</span>
                    </div>
                </div>

                @if(count($this->adminTeachers) > 0)
                    <div class="ledger-band">
                        <p class="label label-ink">Administration</p>
                        <p class="figure">{{ count($this->adminTeachers) }}</p>
                    </div>

                    @foreach($this->adminTeachers as $teacher)
                        @if($teacher->department)
                            @include('frontend.themes.theme_ledger.partials.teacher_card', [
                                'teacher' => $teacher,
                                'faculty' => $teacher->department->faculty,
                                'department' => $teacher->department,
                            ])
                        @endif
                    @endforeach
                @endif

                @if($this->teachers->total() > 0)
                    @if(count($this->adminTeachers) > 0)
                        <div class="ledger-band">
                            <p class="label label-ink">Faculty members</p>
                            <p class="figure">{{ number_format($this->teachers->total()) }}</p>
                        </div>
                    @endif

                    @foreach($this->teachers as $teacher)
                        @if($teacher->department)
                            @include('frontend.themes.theme_ledger.partials.teacher_card', [
                                'teacher' => $teacher,
                                'faculty' => $teacher->department->faculty,
                                'department' => $teacher->department,
                                'showAdminRole' => false,
                            ])
                        @endif
                    @endforeach
                @endif
            </div>

            {{ $this->teachers->links('frontend.themes.theme_ledger.partials.pagination') }}

        @endif
    </div>
</div>
