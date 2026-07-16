<div class="space-y-6">

    <!-- Instant search input -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <input
            type="text"
            wire:model.live.debounce.300ms="q"
            placeholder="Search teachers in this department by name, email, employee ID..."
            class="block w-full pl-10 pr-12 py-3 border border-slate-200 rounded-2xl text-sm bg-white/70 backdrop-blur-xs hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-primary focus:border-diu-primary transition-all placeholder:text-slate-400 shadow-sm"
        />
        @if($q)
            <button wire:click="clearSearch" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors">
                Clear
            </button>
        @endif
    </div>

    <div>
        <h3 class="text-2xl font-extrabold text-gray-900 font-display">
            {{ count($this->adminTeachers) + $this->teachers->total() }} Result{{ (count($this->adminTeachers) + $this->teachers->total()) === 1 ? '' : 's' }}
            @if($q)
                for <span class="text-diu-primary">"{{ $q }}"</span>
            @endif
        </h3>
    </div>

    @if(count($this->adminTeachers) === 0 && $this->teachers->total() === 0)
        <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
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
                            @include('frontend.themes.theme_default.partials.teacher_card', [
                                'teacher' => $teacher,
                                'faculty' => $this->department?->faculty,
                                'department' => $this->department ?? $teacher->department,
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
                            @include('frontend.themes.theme_default.partials.teacher_card', [
                                'teacher' => $teacher,
                                'faculty' => $this->department?->faculty,
                                'department' => $this->department ?? $teacher->department,
                                'showAdminRole' => false,
                            ])
                        @endif
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $this->teachers->links('frontend.themes.theme_default.partials.pagination') }}
                </div>
            </div>
        @endif
    @endif
</div>
