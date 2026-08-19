{{--
    The directory, as one searchable stage.

    Every other theme spends a quarter of the page on a left column holding
    faculties, departments, designations and administrative roles, on every
    screen, whether or not anyone is filtering. Aurora puts the same four
    controls in a command bar across the top: the search field is always there,
    the faculties and departments are a scrolling row of chips beside it, and
    the two long lists that are used least sit behind a drawer.

    That buys the full width for the faces, which is what the page is for.

    Filter state lives in the Livewire component ($q, facultyId, departmentId,
    designationId, adminRoleId); only whether the drawer is open is Alpine's,
    and it is deliberately opened for you when a filter is already applied, so
    an active filter is never hidden behind a closed drawer.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();

    // Whether the drawer has anything in it at all. Within a single department
    // there may be no administrative roles and only one designation, and an
    // empty panel opening under the chips is worse than no control.
    $hasDrawer = $this->visibleDesignations->isNotEmpty()
        || $this->visibleAdminRoles->isNotEmpty()
        || $activeFilters > 0;

    /*
     * What every faculty and department link carries with it.
     *
     * Those links are wire:navigate — a new page — so anything not in the URL
     * is gone. They used to point at a bare /fbe, which meant choosing a
     * faculty silently threw away the designation and the role the reader had
     * picked. Narrowing one axis should not reset the others: if they want
     * "Any" they will say so, and Any is one click away.
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
         empty marker holds the place the bar would occupy if it were not
         sticky. theme.js reads it to decide whether the bar is parked, and to
         put the page back where it was after a filter changes the results. --}}
    <div data-command-anchor aria-hidden="true"></div>

    {{-- Not `.glass`: the command bar carries its own surface, because it has to
         stay readable with photographs scrolling under it. Stacking both would
         also be two classes setting `background`, decided by whichever happens
         to be later in the stylesheet. --}}
    <div class="command command-collapsible"
         x-data="{
            open: false,
            remember: true,
            init() {
                /*
                 * Faculty and department are wire:navigate links, so clicking
                 * one rebuilds this component from scratch and Alpine starts
                 * over — which slammed the drawer shut on someone who had
                 * deliberately opened it, while designation and role (a morph)
                 * left it alone. Same control, two behaviours, for a reason
                 * that is invisible from the outside.
                 *
                 * Remembered for the browsing session rather than forever: it
                 * is how you are working now, not a setting.
                 */
                try { this.open = sessionStorage.getItem('aurora-filters') === 'open'; } catch (e) {}

                // An applied filter is never left hidden behind a shut drawer.
                if (@js($activeFilters > 0)) this.open = true;

                this.$watch('open', (value) => {
                    if (! this.remember) return;

                    try { sessionStorage.setItem('aurora-filters', value ? 'open' : 'shut'); } catch (e) {}
                });
            },
            /*
             * Called from theme.js when the bar parks itself under the header
             * on a small screen, where an open drawer takes most of the phone.
             *
             * The write to sessionStorage is suppressed: this is the page
             * tidying up after itself, not the reader changing their mind, and
             * it must not overwrite the choice they actually made.
             */
            collapse() {
                if (! this.open) return;

                this.remember = false;
                this.open = false;
                this.$nextTick(() => { this.remember = true; });
            }
         }">

        <div class="field">
            <svg class="w-5 h-5 shrink-0" style="color: var(--brand-ink);" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>

            <input type="search"
                   wire:model.live.debounce.300ms="q"
                   placeholder="Search by name, designation, department or faculty"
                   aria-label="Search the faculty directory">

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

        {{-- Faculties. Real links rather than component state, so a faculty is
             an address that can be shared and the back button behaves. --}}
        <div class="chip-rail mt-3" role="list" aria-label="Faculties">
            <a href="{{ route('home', $carry) }}" wire:navigate role="listitem"
               class="chip {{ $this->facultyId ? '' : 'is-active' }}">All faculties</a>

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
                   class="chip {{ (string) $fac->id === (string) $this->facultyId ? 'is-active' : '' }}">
                    {{ $fac->short_name ?: $fac->name }}
                    <span class="chip-count">{{ $fac->teachers_count }}</span>
                </a>
            @endforeach
        </div>

        @if($this->departments->isNotEmpty() && $this->selectedFaculty)
            <div class="chip-rail mt-2" role="list" aria-label="Departments">
                @foreach($this->departments as $dept)
                    <a href="{{ route('department.show', array_merge([
                            'faculty_short_name' => strtolower($this->selectedFaculty->short_name),
                            'department_code' => strtolower($dept->code),
                       ], $carry)) }}" wire:navigate role="listitem"
                       class="chip {{ (string) $dept->id === (string) $this->departmentId ? 'is-active' : '' }}"
                       style="font-weight: 500;">
                        {{ $dept->short_name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($hasDrawer)
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

            @if($this->facultyId || $this->departmentId || $activeFilters)
                <a href="{{ route('home') }}" wire:navigate class="inline-block text-[12px] font-semibold link-brand">
                    Clear everything
                </a>
            @endif
        </div>
        @endif
    </div>

    {{-- Results. Dimmed rather than emptied while Livewire is in flight: the
         previous faces staying put is far less jarring than the page blanking
         and refilling on every keystroke.

         The id gives the listing a stable address — #results on any directory
         URL lands on the faces rather than the top of the page. --}}
    <div id="results" class="mt-8" wire:loading.class="is-busy" wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

        <div class="flex flex-wrap items-baseline justify-between gap-3 mb-5">
            <h2 class="display-md">
                @if($q)
                    {{ number_format($totalResults) }} {{ \Illuminate\Support\Str::plural('result', $totalResults) }} for
                    <span class="display-spectrum">&ldquo;{{ $q }}&rdquo;</span>
                @elseif($this->selectedFaculty)
                    {{ $this->selectedFaculty->name }}
                @else
                    Everyone at {{ \App\Helpers\Branding::get('short_name') }}
                @endif
            </h2>

            <p class="numeral">{{ number_format($this->staticTeacherCount) }} faculty members</p>
        </div>

        @if($totalResults === 0)

            <div class="empty">
                <p class="display-md mb-2">Nobody matches that.</p>
                <p class="text-[14px]">Try a shorter search, or clear the filters above.</p>
            </div>

        @else

            @if(count($this->adminTeachers) > 0)
                <section class="mb-10">
                    <p class="eyebrow mb-4">Administration</p>
                    <div class="tile-grid">
                        @foreach($this->adminTeachers as $teacher)
                            @if($teacher->department)
                                @include('frontend.themes.theme_aurora.partials.teacher_card', [
                                    'teacher' => $teacher,
                                    'faculty' => $teacher->department->faculty,
                                    'department' => $teacher->department,
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
                                    'faculty' => $teacher->department->faculty,
                                    'department' => $teacher->department,
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
