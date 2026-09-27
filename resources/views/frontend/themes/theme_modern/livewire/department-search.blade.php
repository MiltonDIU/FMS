{{--
    One department — its people, or its offices.

    The same sidebar and the same search bar as the main directory, so moving
    between the two does not change where anything is.

    The two faces of a department are real URLs (department.show and
    department.contact) rather than component state, so both can be linked and
    the back button behaves; wire:navigate makes the swap feel like a tab. The
    contacts used to be a separate page with no navigation on it, which made
    reaching the next department's offices a trip back through the directory.
--}}
@php
    $activeFilters = collect([$this->designationId, $this->adminRoleId])->filter()->count();
    $totalResults = count($this->adminTeachers) + $this->teachers->total();
    $isContact = $this->view === 'contact';

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

    $deptRoute = ($this->department && $this->department->faculty?->short_name && $this->department->code)
        ? [
            'faculty_short_name' => strtolower($this->department->faculty->short_name),
            'department_code' => strtolower($this->department->code),
        ]
        : null;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- SIDEBAR -->
    <aside class="hidden lg:block lg:col-span-1">
        <div class="lg:sticky" style="top: calc(var(--header-h) + 1rem);">
            @include('frontend.themes.theme_modern.partials.filters_department', ['carry' => $carry, 'deptRoute' => $deptRoute, 'idPrefix' => 'sb'])
        </div>
    </aside>

    <!-- MAIN STAGE -->
    <div class="lg:col-span-3 space-y-6">

        @if($this->department)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">
                        {{ $isContact ? 'Contact Directory' : 'Department Active' }}
                    </span>
                    <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $this->department->name }}</h2>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        {{ number_format($this->totalMembers) }} Faculty Members
                        @if($this->department->faculty?->name)
                            <span class="text-slate-300">•</span> <span class="text-slate-400">{{ $this->department->faculty->name }}</span>
                        @endif
                    </p>
                </div>

                @if($deptRoute)
                    {{-- Both faces of the department, as a switch. Both carry the
                         filters, so stepping over to the contacts and back
                         returns you to the list you left rather than to an
                         unfiltered one. --}}
                    <nav class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 shrink-0" aria-label="Department views">
                        <a href="{{ route('department.show', array_merge($deptRoute, $carry)) }}" wire:navigate
                           @if(! $isContact) aria-current="page" @endif
                           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-colors {{ $isContact ? 'text-slate-600 hover:text-slate-900' : 'bg-diu-primary text-white shadow-sm' }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Faculty Members
                        </a>
                        <a href="{{ route('department.contact', array_merge($deptRoute, $carry)) }}" wire:navigate
                           @if($isContact) aria-current="page" @endif
                           class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg transition-colors {{ $isContact ? 'bg-diu-primary text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            Contact
                        </a>
                    </nav>
                @endif
            </div>
        @endif

        {{-- Holds the bar's non-sticky position for theme.js — see the note in
             teacher-search. --}}
        <div data-command-anchor aria-hidden="true"></div>

        <div class="modern-command"
             x-data="{
                open: false,
                init() {
                    // See the note in teacher-search: every faculty and department
                    // link is a wire:navigate, which rebuilds this component and
                    // would otherwise shut a drawer the reader opened.
                    try { this.open = sessionStorage.getItem('modern-filters') === 'open'; } catch (e) {}

                    if (@js($activeFilters > 0)) this.open = true;

                    this.$watch('open', (value) => {
                        try { sessionStorage.setItem('modern-filters', value ? 'open' : 'shut'); } catch (e) {}
                    });
                }
             }">

            <div class="flex items-center gap-2">
                @if($isContact)
                    {{-- Filters narrow a list of people, so the field is gone while
                         the contacts are up; the navigation stays, which is the
                         reason contacts live in this layout at all. --}}
                    <p class="flex-1 min-w-0 text-sm font-semibold text-slate-600 truncate px-1">
                        Office contacts · {{ $this->department?->name }}
                    </p>
                @else
                    <div class="relative flex-1 min-w-0">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="q"
                            placeholder="Search teachers in this department by name, email, employee ID..."
                            aria-label="Search this department"
                            class="modern-search block w-full pl-10 pr-16 py-3 border border-slate-200 rounded-2xl text-sm bg-white/70 backdrop-blur-xs hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-primary focus:border-diu-primary transition-all placeholder:text-slate-400 shadow-sm"
                        />
                        @if($q)
                            <button type="button" wire:click="clearSearch" aria-label="Clear search"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                                Clear
                            </button>
                        @endif
                    </div>
                @endif

                <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                        class="lg:hidden inline-flex items-center gap-1.5 shrink-0 h-11 px-3.5 rounded-2xl border text-xs font-bold transition-colors"
                        :class="open ? 'bg-diu-primary text-white border-diu-primary' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        aria-label="{{ $isContact ? 'Toggle department navigation' : 'Toggle filters' }}">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                    <span class="hidden sm:inline">{{ $isContact ? 'Departments' : 'Filters' }}</span>
                    @if($activeFilters && ! $isContact)
                        <span class="text-[10px] font-extrabold px-1.5 rounded-md bg-diu-accent text-white">{{ $activeFilters }}</span>
                    @endif
                </button>

                {{-- Same button, same session-remembered state on both views; what
                     the bubble it folds into offers to give back is all that
                     differs, hence the two data attributes — see the fold module
                     in theme.js. --}}
                <button type="button" data-command-fold
                        @if($isContact)
                            data-command-restore="Show department navigation"
                            data-command-glyph="nav"
                        @endif
                        class="inline-flex items-center justify-center shrink-0 w-11 h-11 rounded-2xl border border-slate-200 bg-white text-slate-500 hover:text-diu-primary hover:border-diu-primary/40 transition-colors"
                        aria-label="{{ $isContact ? 'Hide department navigation' : 'Hide search and filters' }}"
                        title="{{ $isContact ? 'Hide department navigation' : 'Hide search and filters' }}">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg>
                </button>
            </div>

            <div x-show="open" x-cloak class="lg:hidden mt-4 pt-4 border-t border-slate-200">
                @include('frontend.themes.theme_modern.partials.filters_department', ['carry' => $carry, 'deptRoute' => $deptRoute, 'idPrefix' => 'dr'])
            </div>
        </div>

        <div id="results" class="space-y-6" wire:loading.class="is-busy"
             wire:target="q, setDesignation, setAdmin, gotoPage, nextPage, previousPage">

            @if($isContact)

                @include('frontend.themes.theme_modern.partials.department_contacts', [
                    'contacts' => $this->contacts,
                    'department' => $this->department,
                ])

            @else

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
                                            'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                            'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
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
                                            'faculty' => $this->all ? ($teacher->department->faculty ?? null) : ($this->department?->faculty),
                                            'department' => $this->all ? $teacher->department : ($this->department ?? $teacher->department),
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

            @endif
        </div>
    </div>
</div>
