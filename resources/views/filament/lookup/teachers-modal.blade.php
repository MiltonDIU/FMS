<div class="space-y-4">
    @if($teachers->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
            <svg class="w-12 h-12 mb-3 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span class="text-sm font-medium">No teachers linked to this category yet.</span>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($teachers as $teacher)
                <div class="flex items-center space-x-4 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/50 hover:bg-gray-50 dark:hover:bg-gray-900 hover:shadow-md hover:border-primary-500 dark:hover:border-primary-500 transition duration-200">
                    <!-- Photo -->
                    <div class="flex-shrink-0">
                        <img src="{{ $teacher->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?background=random&color=fff&name=' . urlencode($teacher->full_name) }}" 
                             alt="{{ $teacher->full_name }}" 
                             class="h-14 w-14 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800" />
                    </div>
                    <!-- Details -->
                    <div class="flex-grow min-w-0">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $teacher->full_name }}
                        </h4>
                        
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate flex items-center mt-1">
                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ $teacher->email ?? $teacher->secondary_email ?? '—' }}
                        </p>

                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <!-- Faculty Badge -->
                            @if($facultyName = ($teacher->department?->faculty?->short_name ?: ($teacher->department?->faculty?->code ?: $teacher->department?->faculty?->name)))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900/50">
                                    {{ $facultyName }}
                                </span>
                            @endif

                            <!-- Department Badge -->
                            @if($deptName = ($teacher->department?->short_name ?: ($teacher->department?->code ?: $teacher->department?->name)))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50">
                                    {{ $deptName }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
