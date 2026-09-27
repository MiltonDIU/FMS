{{--
    The directory: the filter sidebar beside the search and the cards.

    The sidebar is how this theme looks from the large breakpoint up, and it
    stays. Below that breakpoint it used to stack above the results in full, so
    on a phone the first face sat under every faculty, department and
    designation; there the same lists live in a drawer under the search field
    instead, opened for you whenever a filter is already applied, so an active
    filter is never hidden behind a closed drawer.

    Filter state lives in the Livewire component ($q, facultyId, departmentId,
    designationId, adminRoleId); only whether the drawer is open is Alpine's.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();

    /*
     * What every faculty and department link carries with it.
     *
     * Those links are wire:navigate — a new page — so anything not in the URL
     * is gone. They used to point at a bare /fbe, which meant choosing a
     * faculty silently threw away the designation and the role the reader had
     * picked. Narrowing one axis should not reset the others.
     *
     * The search text travels for the same reason: typing a name and then
     * picking a faculty reads as "that name, within this faculty".
     *
     * The component reads all three back in mount(), and the canonical URL is
     * built from the route's path parameters only — so none of this adds a
     * second address for the same page.
     */
    $carry = array_filter([
        'q' => trim($q),
        'designation' => $this->designationId,
        'admin' => $this->adminRoleId,
    ], 'filled');
@endphp

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- SIDEBAR -->
    <aside class="hidden lg:block lg:col-span-1">
        <div class="lg:sticky" style="top: calc(var(--header-h) + 1rem);">
            @include('frontend.themes.theme_modern.partials.filters_directory', ['carry' => $carry, 'idPrefix' => 'sb'])
        </div>
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        @if($this->selectedFaculty)
            <div>
                <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Faculty Active</span>
                <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $this->selectedFaculty->name }}</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ number_format($this->staticTeacherCount) }} Faculty Members
                </p>
            </div>
        @else
            <div>
                <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">All Faculties</span>
                <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ \App\Helpers\Branding::get('site_name') }}</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ number_format($this->staticTeacherCount) }} Faculty Members
                </p>
            </div>
        @endif

        {{-- A sticky element reports its stuck position, not its real one, so this
             empty marker holds the place the bar would occupy if it were not
             sticky. theme.js reads it to decide whether the bar is parked, and to
             put the page back where it was after a filter changes the results. --}}
        <div data-command-anchor aria-hidden="true"></div>

        {{-- The search bar. Parks under the header from the large breakpoint up;
             see .modern-command in theme.css. --}}
        <div class="modern-command"
             x-data="{
                open: false,
                init() {
                    /*
                     * Faculty and department are wire:navigate links, so choosing
                     * one rebuilds this component and Alpine starts over — which
                     * would slam the drawer shut on someone who had deliberately
                     * opened it. Remembered for the browsing session rather than
                     * forever: it is how you are working now, not a setting.
                     */
                    try { this.open = sessionStorage.getItem('modern-filters') === 'open'; } catch (e) {}

                    // An applied filter is never left hidden behind a shut drawer.
                    if (@js($activeFilters > 0)) this.open = true;

                    this.$watch('open', (value) => {
                        try { sessionStorage.setItem('modern-filters', value ? 'open' : 'shut'); } catch (e) {}
                    });
                }
             }">

            <div class="flex items-center gap-2">
                <div class="relative flex-1 min-w-0">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="q"
                        placeholder="Search teachers by name, email, department, faculty, designation..."
                        aria-label="Search the faculty directory"
                        class="modern-search block w-full pl-10 pr-16 py-3 border border-slate-200 rounded-2xl text-sm bg-white/70 backdrop-blur-xs hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-primary focus:border-diu-primary transition-all placeholder:text-slate-400 shadow-sm"
                    />
                    @if($q)
                        <button type="button" wire:click="clearSearch" aria-label="Clear search"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                            Clear
                        </button>
                    @endif
                </div>

                {{-- Below the large breakpoint the sidebar lives in the drawer
                     under this row. --}}
                <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                        class="lg:hidden inline-flex items-center gap-1.5 shrink-0 h-11 px-3.5 rounded-2xl border text-xs font-bold transition-colors"
                        :class="open ? 'bg-diu-primary text-white border-diu-primary' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        aria-label="Toggle filters">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                    <span class="hidden sm:inline">Filters</span>
                    @if($activeFilters || $this->facultyId || $this->departmentId)
                        <span class="text-[10px] font-extrabold px-1.5 rounded-md bg-diu-accent text-white">
                            {{ $activeFilters + ($this->facultyId ? 1 : 0) + ($this->departmentId ? 1 : 0) }}
                        </span>
                    @endif
                </button>

                {{-- Fold the whole bar away into a bubble that can be dragged
                     wherever the reader wants it, and tapped to bring the bar back.
                     See .command-bubble in theme.css and the fold module in
                     theme.js, which owns the bubble, its place and the drag. --}}
                <button type="button" data-command-fold
                        class="inline-flex items-center justify-center shrink-0 w-11 h-11 rounded-2xl border border-slate-200 bg-white text-slate-500 hover:text-diu-primary hover:border-diu-primary/40 transition-colors"
                        aria-label="Hide search and filters" title="Hide search and filters">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg>
                </button>
            </div>

            <div x-show="open" x-cloak class="lg:hidden mt-4 pt-4 border-t border-slate-200">
                @include('frontend.themes.theme_modern.partials.filters_directory', ['carry' => $carry, 'idPrefix' => 'dr'])
            </div>
        </div>

        {{-- Results. Dimmed rather than emptied while Livewire is in flight: the
             previous cards staying put is far less jarring than the page blanking
             and refilling on every keystroke.

             The id gives the listing a stable address — #results on any
             directory URL lands on the cards rather than the top of the page. --}}
        <div id="results" class="space-y-6" wire:loading.class="is-busy"
             wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

            <div>
                <h3 class="text-2xl font-extrabold text-gray-900 font-display">
                    {{ number_format($totalResults) }} {{ \Illuminate\Support\Str::plural('Result', $totalResults) }}
                    @if($q)
                        for <span class="text-diu-primary">"{{ $q }}"</span>
                    @endif
                </h3>
            </div>

            @if($totalResults === 0)
                <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-3xl p-12 text-center shadow-sm">
                    <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <p class="text-gray-500 font-semibold">No teachers found.</p>
                    <p class="text-xs text-slate-400 mt-1">Try a different keyword or clear the active filters.</p>
                </div>
            @else
                @if(count($this->adminTeachers) > 0)
                    <!-- Administrative Members Section -->
                    <div class="space-y-4 mb-8">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-1 bg-diu-accent rounded-xs"></div>
                            <h4 class="font-display font-bold text-md text-gray-800">Administration</h4>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($this->adminTeachers as $teacher)
                                @if($teacher->department)
                                    @include('frontend.themes.theme_modern.partials.teacher_card', [
                                        'teacher' => $teacher,
                                        'faculty' => $teacher->department->faculty,
                                        'department' => $teacher->department,
                                    ])
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($this->teachers->total() > 0)
                    <!-- General Faculty Members Section -->
                    <div class="space-y-4">
                        @if(count($this->adminTeachers) > 0)
                            <div class="flex items-center gap-2">
                                <div class="h-4 w-1 bg-diu-primary rounded-xs"></div>
                                <h4 class="font-display font-bold text-md text-gray-800">Faculty Members</h4>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($this->teachers as $teacher)
                                @if($teacher->department)
                                    @include('frontend.themes.theme_modern.partials.teacher_card', [
                                        'teacher' => $teacher,
                                        'faculty' => $teacher->department->faculty,
                                        'department' => $teacher->department,
                                        'showAdminRole' => false,
                                    ])
                                @endif
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $this->teachers->links('frontend.themes.theme_modern.partials.pagination') }}
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
