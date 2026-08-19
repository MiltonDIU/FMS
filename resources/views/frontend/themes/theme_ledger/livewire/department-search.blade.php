{{--
    One department — its people, or its offices.

    The same finder and the same ledger as the main directory, so moving between
    the two does not change where anything is. The department's siblings sit in
    the index rail, which makes stepping from one department to the next one
    click from anywhere on the page rather than a trip back through the faculty.

    The two faces of a department are real URLs (department.show and
    department.contact) rather than component state, so both can be linked and
    the back button behaves; wire:navigate makes the swap feel immediate.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();

    // Whether the drawer has anything in it. A small department may have no
    // administrative roles and a single designation, and an empty panel opening
    // under the index is worse than no control at all.
    $hasDrawer = $this->view !== 'contact'
        && ($this->visibleDesignations->isNotEmpty()
            || $this->visibleAdminRoles->isNotEmpty()
            || $activeFilters > 0);

    /*
     * Carried by every faculty and department link — see the note in
     * teacher-search. Moving to the next department should narrow what you are
     * already looking at, not throw it away and start over.
     *
     * On this page these three are #[Url] properties, so they are in the address
     * bar already; putting them on the links keeps them there across the jump.
     */
    $carry = array_filter([
        'q' => trim($q),
        'designation' => $this->designationId,
        'admin' => $this->adminRoleId,
    ], 'filled');

    $deptRoute = ($this->department && $this->department->faculty)
        ? [
            'faculty_short_name' => strtolower($this->department->faculty->short_name),
            'department_code' => strtolower($this->department->code),
        ]
        : null;
@endphp

<div>

    @if($this->department)
        <div class="pb-4 mb-5 rule-double-b">
            <p class="label mb-3">{{ $this->department->faculty?->name ?? 'Department' }}</p>

            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3">
                <h1 class="title-lg">{{ $this->department->name }}</h1>

                <p class="figure">
                    {{ number_format($this->totalMembers) }} faculty members
                    @if($this->department->code)
                        <span aria-hidden="true">·</span> {{ strtoupper($this->department->code) }}
                    @endif
                </p>
            </div>

            @if($deptRoute)
                {{-- Both carry the filters, so stepping over to the contacts and
                     back returns you to the list you left rather than to an
                     unfiltered one. The contacts view ignores them; they are
                     only along for the return trip. --}}
                <nav class="index-rail mt-3" aria-label="Department views">
                    <a href="{{ route('department.show', array_merge($deptRoute, $carry)) }}" wire:navigate
                       class="index-link {{ $this->view === 'contact' ? '' : 'is-active' }}">Faculty members</a>
                    <a href="{{ route('department.contact', array_merge($deptRoute, $carry)) }}" wire:navigate
                       class="index-link {{ $this->view === 'contact' ? 'is-active' : '' }}">Contact</a>
                </nav>
            @endif
        </div>
    @endif

    {{-- Holds the finder's non-sticky position for theme.js — see the note in
         teacher-search. --}}
    <div data-finder-anchor aria-hidden="true"></div>

    <div class="finder"
         x-data="{
            open: false,
            init() {
                // See the note in teacher-search: every faculty and department
                // link is a wire:navigate, which rebuilds this component and
                // would otherwise shut a drawer the reader opened.
                try { this.open = sessionStorage.getItem('ledger-refine') === 'open'; } catch (e) {}

                if (@js($activeFilters > 0)) this.open = true;

                this.$watch('open', (value) => {
                    try { sessionStorage.setItem('ledger-refine', value ? 'open' : 'shut'); } catch (e) {}
                });
            }
         }">

        {{-- Filters narrow a list of people, so the field and the drawer are
             gone while the contacts view is up. The navigation rails below stay,
             which is the whole reason contacts live in this layout instead of on
             a page of their own. --}}
        @if($this->view !== 'contact')
            <div class="finder-field">
                <svg class="w-4 h-4 shrink-0" style="color: var(--ink-4);" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>

                <input type="search"
                       wire:model.live.debounce.300ms="q"
                       placeholder="Search this department by name, designation or employee ID"
                       aria-label="Search this department">

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
        @endif

        <div class="index-rail index-scroll {{ $this->view === 'contact' ? '' : 'pt-1.5' }}"
             role="list" aria-label="Faculties">
            <a href="{{ route('home', $carry) }}" wire:navigate role="listitem" class="index-link">All</a>

            @foreach($this->facultyList as $fac)
                {{-- Built here rather than through $fac->url, which takes no
                     query parameters. Same fallback as the accessor, since
                     short_name is nullable and route() would throw. --}}
                @php
                    $facUrl = $fac->short_name
                        ? route('faculty.show', array_merge(
                            ['faculty_short_name' => strtolower($fac->short_name)],
                            $carry,
                        ))
                        : route('home', $carry);
                @endphp

                <a href="{{ $facUrl }}" wire:navigate role="listitem"
                   class="index-link {{ (! $this->all && $this->department && $fac->id === $this->department->faculty_id) ? 'is-active' : '' }}">
                    {{ $fac->short_name ?: $fac->name }}
                </a>
            @endforeach
        </div>

        @if($this->departmentList->isNotEmpty() && $this->department?->faculty)
            <div class="index-rail index-scroll" style="border-top: 1px solid var(--rule-soft);"
                 role="list" aria-label="Departments in this faculty">
                @foreach($this->departmentList as $dept)
                    <a href="{{ route('department.show', array_merge([
                            'faculty_short_name' => strtolower($this->department->faculty->short_name),
                            'department_code' => strtolower($dept->code),
                       ], $carry)) }}" wire:navigate role="listitem"
                       class="index-link {{ $dept->id === $this->department->id ? 'is-active' : '' }}"
                       style="font-weight: 500;">
                        {{ $dept->short_name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($this->view !== 'contact' && $hasDrawer)
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

                @if($activeFilters && $deptRoute)
                    <a href="{{ route('department.show', $deptRoute) }}" wire:navigate
                       class="inline-block text-[12px] font-semibold link">Clear filters</a>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-6" wire:loading.class="is-busy"
         wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

        @if($this->view === 'contact')

            @include('frontend.themes.theme_ledger.partials.department_contacts', [
                'contacts' => $this->contacts,
                'department' => $this->department,
            ])

        @elseif($totalResults === 0)

            <div class="empty">
                <p class="title-md mb-1">Nobody matches that.</p>
                <p class="text-[14px]">Try a shorter search, or clear the filters above.</p>
            </div>

        @else

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
                                'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
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
                                'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
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
