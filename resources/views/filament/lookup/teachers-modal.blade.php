<div class="space-y-4">
    @if($teachers->isEmpty())
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            No teachers found for this entry.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg shadow-sm">
            <table class="min-w-full border-collapse border border-gray-200 dark:border-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="border border-gray-200 dark:border-gray-700 px-4 py-3 text-left">Photo</th>
                        <th class="border border-gray-200 dark:border-gray-700 px-4 py-3 text-left">Name</th>
                        <th class="border border-gray-200 dark:border-gray-700 px-4 py-3 text-left">Email</th>
                        <th class="border border-gray-200 dark:border-gray-700 px-4 py-3 text-left">Faculty</th>
                        <th class="border border-gray-200 dark:border-gray-700 px-4 py-3 text-left">Department</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                    @foreach($teachers as $teacher)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="border border-gray-200 dark:border-gray-700 px-4 py-3 whitespace-nowrap">
                                <img src="{{ $teacher->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?background=random&color=fff&name=' . urlencode($teacher->full_name) }}" 
                                     alt="{{ $teacher->full_name }}" 
                                     class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700" />
                            </td>
                            <td class="border border-gray-200 dark:border-gray-700 px-4 py-3 font-medium whitespace-nowrap">
                                {{ $teacher->full_name }}
                            </td>
                            <td class="border border-gray-200 dark:border-gray-700 px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->email ?? $teacher->secondary_email ?? '—' }}
                            </td>
                            <td class="border border-gray-200 dark:border-gray-700 px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->department?->faculty?->short_name ?: ($teacher->department?->faculty?->code ?: ($teacher->department?->faculty?->name ?? '—')) }}
                            </td>
                            <td class="border border-gray-200 dark:border-gray-700 px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->department?->short_name ?: ($teacher->department?->code ?: ($teacher->department?->name ?? '—')) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
