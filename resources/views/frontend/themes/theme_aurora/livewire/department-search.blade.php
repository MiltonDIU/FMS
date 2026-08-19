{{--
    A single department — its people, or its offices.

    Same command bar as the main directory, so moving between the two does not
    change where anything is. The department's siblings sit in the chip rail,
    which means stepping from one department to the next is one click from
    anywhere on the page rather than a trip back through the faculty.

    The two faces of a department are real URLs (department.show and
    department.contact) rather than component state, so both can be linked and
    the back button behaves; wire:navigate makes the swap feel like a tab.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();

    // Whether the drawer has anything in it. A small department may have no
    // administrative roles and a single designation, and an empty panel opening
    // under the chips is worse than no control at all.
    $hasDrawer = $this->view !== 'contact'
        && ($this->visibleDesignations->isNotEmpty()
            || $this->visibleAdminRoles->isNotEmpty()
            || $activeFilters > 0);

    /*
     * Carried by every faculty and department link — see the note in
     * teacher-search. Moving to the next department should narrow what you are
     * already looking at, not throw it away and start over.
     *
     * On this page these three are #[Url] properties, so they are in the
     * address bar already; putting them on the links keeps them there across
     * the jump.
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
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 mb-7">
            <div class="min-w-0">
                <p class="eyebrow mb-2">
                    {{ $this->department->faculty?->name ?? 'Department' }}
                </p>
                <h1 class="display-lg">{{ $this->department->name }}</h1>
                <p class="numeral mt-2">
                    {{ number_format($this->totalMembers) }} faculty members
                    @if($this->department->code)
                        <span style="color: var(--hairline-strong);">·</span> {{ strtoupper($this->department->code) }}
                    @endif
                </p>
            </div>

            @if($deptRoute)
                <nav class="flex gap-2 shrink-0" aria-label="Department views">
                    {{-- Both carry the filters too, so stepping over to the
                         contacts and back returns you to the list you left
                         rather than to an unfiltered one. The contacts view
                         ignores them; they are only along for the return trip. --}}
                    <a href="{{ route('department.show', array_merge($deptRoute, $carry)) }}" wire:navigate
                       class="chip {{ $this->view === 'contact' ? '' : 'is-active' }}">Faculty members</a>
                    <a href="{{ route('department.contact', array_merge($deptRoute, $carry)) }}" wire:navigate
                       class="chip {{ $this->view === 'contact' ? 'is-active' : '' }}">Contact</a>
                </nav>
            @endif
        </div>
    @endif

    {{-- Holds the bar's non-sticky position for theme.js — see the note in
         teacher-search. --}}
    <div data-command-anchor aria-hidden="true"></div>

    {{-- Not `.glass` — see the note in teacher-search: this panel owns its own
         surface so it stays readable with content scrolling under it.

         The drawer state is remembered for the same reason it is there: every
         faculty and department chip is a wire:navigate link, which rebuilds
         this component and would otherwise shut a drawer the reader opened. --}}
    {{-- command-collapsible only when there is a search row to fall back to.
         The contacts view carries nothing but the rails, and collapsing those
         would pin an empty box under the header. --}}
    <div class="command @if($this->view !== 'contact') command-collapsible @endif"
         x-data="{
            open: false,
            remember: true,
            init() {
                try { this.open = sessionStorage.getItem('aurora-filters') === 'open'; } catch (e) {}

                if (@js($activeFilters > 0)) this.open = true;

                this.$watch('open', (value) => {
                    if (! this.remember) return;

                    try { sessionStorage.setItem('aurora-filters', value ? 'open' : 'shut'); } catch (e) {}
                });
            },
            // See the note in teacher-search: an automatic collapse on a small
            // screen must not be recorded as the reader's own choice.
            collapse() {
                if (! this.open) return;

                this.remember = false;
                this.open = false;
                this.$nextTick(() => { this.remember = true; });
            }
         }">

        {{-- Filters narrow a list of people, so the field and the drawer are
             gone while the contacts view is up. The navigation rails below stay,
             which is the whole reason contacts live in this layout instead of on
             a page of their own. --}}
        @if($this->view !== 'contact')
            <div class="field">
                <svg class="w-5 h-5 shrink-0" style="color: var(--brand-ink);" viewBox="0 0 24 24" fill="none"
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
                             stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                @endif

                <span class="numeral hidden sm:block shrink-0 pl-1">{{ number_format($totalResults) }}</span>

                @if($hasDrawer)
                    <button type="button" class="chip shrink-0" :class="{ 'is-active': open }" @click="open = ! open"
                            aria-label="Toggle filters">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h18M7 12h10M11 18h2"/>
                        </svg>
                        <span class="hidden sm:inline">Filters</span>
                        @if($activeFilters)
                            <span class="chip-count">{{ $activeFilters }}</span>
                        @endif
                    </button>
                @endif

                {{-- Fold the whole bar away into a bubble that can be dragged wherever
                     the reader's thumb is, and tapped to bring the bar back. Small
                     screens only; see .command-bubble in theme.css and the fold module
                     in theme.js, which owns the bubble, its place and the drag. --}}
                <button type="button" data-command-fold class="btn-icon command-fold shrink-0"
                        aria-label="Hide search and filters" title="Hide search and filters">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- The fold control lives in the search row, and the contacts view has
             no search row — which left the one page whose bar is nothing but
             navigation as the one page a phone reader could not put the bar
             away on. It rides alongside the faculty rail here instead, so it
             costs no row of its own. --}}
        <div class="chip-rail-row flex items-center gap-2 {{ $this->view === 'contact' ? '' : 'mt-3' }}">
            <div class="chip-rail min-w-0 flex-1" role="list" aria-label="Faculties">
                <a href="{{ route('home', $carry) }}" wire:navigate role="listitem" class="chip">All faculties</a>

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
                       class="chip {{ (! $this->all && $this->department && $fac->id === $this->department->faculty_id) ? 'is-active' : '' }}">
                        {{ $fac->short_name ?: $fac->name }}
                    </a>
                @endforeach
            </div>

            {{-- Same button, same place on the screen, same session-remembered
                 state as the one in the search row above; what the bubble it
                 folds into offers to give back is all that differs, hence the
                 two data attributes — see the fold module in theme.js. --}}
            @if($this->view === 'contact')
                <button type="button" data-command-fold
                        data-command-restore="Show department navigation"
                        data-command-glyph="nav"
                        class="btn-icon command-fold shrink-0"
                        aria-label="Hide department navigation" title="Hide department navigation">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/>
                    </svg>
                </button>
            @endif
        </div>

        @if($this->departmentList->isNotEmpty() && $this->department?->faculty)
            <div class="chip-rail mt-2" role="list" aria-label="Departments in this faculty">
                @foreach($this->departmentList as $dept)
                    <a href="{{ route('department.show', array_merge([
                            'faculty_short_name' => strtolower($this->department->faculty->short_name),
                            'department_code' => strtolower($dept->code),
                       ], $carry)) }}" wire:navigate role="listitem"
                       class="chip {{ $dept->id === $this->department->id ? 'is-active' : '' }}"
                       style="font-weight: 500;">
                        {{ $dept->short_name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($this->view !== 'contact' && $hasDrawer)
            <div x-show="open" x-cloak class="mt-4 pt-4 space-y-4" style="border-top: 1px solid var(--hairline-soft);">

                @if($this->visibleDesignations->isNotEmpty())
                    <div>
                        <p class="eyebrow-quiet mb-2">Designation</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="setDesignation(null)"
                                    class="chip {{ $this->designationId ? '' : 'is-active' }}">Any</button>
                            @foreach($this->visibleDesignations as $desig)
                                <button type="button" wire:click="setDesignation({{ $desig->id }})"
                                        class="chip {{ $this->designationId == $desig->id ? 'is-active' : '' }}">
                                    {{ $desig->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($this->visibleAdminRoles->isNotEmpty())
                    <div>
                        <p class="eyebrow-quiet mb-2">Administrative role</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="setAdmin(null)"
                                    class="chip {{ $this->adminRoleId ? '' : 'is-active' }}">Any</button>
                            @foreach($this->visibleAdminRoles as $role)
                                <button type="button" wire:click="setAdmin({{ $role->id }})"
                                        class="chip {{ $this->adminRoleId == $role->id ? 'is-active' : '' }}">
                                    {{ $role->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($activeFilters && $deptRoute)
                    <a href="{{ route('department.show', $deptRoute) }}" wire:navigate
                       class="inline-block text-[12px] font-semibold link-brand">Clear filters</a>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-8" wire:loading.class="is-busy" wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

        @if($this->view === 'contact')

            @include('frontend.themes.theme_aurora.partials.department_contacts', [
                'contacts' => $this->contacts,
                'department' => $this->department,
            ])

        @elseif($totalResults === 0)

            <div class="empty">
                <p class="display-md mb-2">Nobody matches that.</p>
                <p class="text-[14px]">Try a shorter search, or clear the filters above.</p>
            </div>

        @else

            @if($q)
                <p class="numeral mb-5">
                    {{ number_format($totalResults) }} {{ \Illuminate\Support\Str::plural('result', $totalResults) }}
                    for &ldquo;{{ $q }}&rdquo;
                </p>
            @endif

            @if(count($this->adminTeachers) > 0)
                <section class="mb-10">
                    <p class="eyebrow mb-4">Administration</p>
                    <div class="tile-grid">
                        @foreach($this->adminTeachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_aurora.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                    'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
                                ])
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            @if($this->teachers->total() > 0)
                <section>
                    @if(count($this->adminTeachers) > 0)
                        <p class="eyebrow-quiet mb-4">Faculty members</p>
                    @endif

                    <div class="tile-grid">
                        @foreach($this->teachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_aurora.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                    'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
                                    'showAdminRole' => false,
                                ])
                            @endif
                        @endforeach
                    </div>

                    {{ $this->teachers->links('frontend.themes.theme_aurora.partials.pagination') }}
                </section>
            @endif

        @endif
    </div>
</div>
