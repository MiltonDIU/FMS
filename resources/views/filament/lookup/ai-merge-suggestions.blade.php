<div class="space-y-6">
    @if(empty($suggestions))
        <div class="flex flex-col items-center justify-center p-8 text-center bg-gray-50 dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-950 rounded-full text-emerald-600 dark:text-emerald-400 mb-3">
                <svg class="w-8 h-8" width="32" height="32" style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">All Clear!</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mt-1">
                AI did not find any potential duplicate groups. Your database lookup values look clean and well-structured!
            </p>
        </div>
    @else
        <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-lg p-4 flex items-start space-x-3">
            <div class="text-amber-500 mt-0.5">
                <svg class="w-5 h-5" width="20" height="20" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Review AI Merge Suggestions</h4>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                    Below are the duplicate groups identified. For each group, choose which name to keep as the primary target. Merging will update all associated teachers and delete the duplicates.
                </p>
            </div>
        </div>

        <div class="space-y-4">
            @foreach($suggestions as $index => $group)
                <div class="group-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm hover:shadow-md transition duration-200">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-3 flex-1">
                            <div class="flex items-center space-x-2">
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Potential Duplicate Group #{{ $index + 1 }}
                                </h4>
                            </div>
                            
                            <!-- Badges of items to be merged -->
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    {{ $group['primary']['name'] }} (AI Target)
                                </span>
                                @foreach($group['duplicates'] as $dup)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                        {{ $dup['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <!-- Merge Controls -->
                        <div class="flex items-center space-x-3 min-w-[280px]">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Keep Name:</label>
                                <select class="merge-target-select block w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 transition duration-150">
                                    <option value="{{ $group['primary']['id'] }}">{{ $group['primary']['name'] }}</option>
                                    @foreach($group['duplicates'] as $dup)
                                        <option value="{{ $dup['id'] }}">{{ $dup['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="pt-5">
                                <button 
                                    type="button"
                                    wire:click="mergeGroup($event.target.closest('.group-card').querySelector('.merge-target-select').value, {{ json_encode(array_merge([$group['primary']['id']], array_column($group['duplicates'], 'id'))) }}, '{{ $type }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-lg text-white bg-amber-600 hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 shadow-sm transition duration-150"
                                >
                                    Merge Group
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
