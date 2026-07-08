<div class="space-y-4">
    @if($teachers->isEmpty())
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            No teachers found for this entry.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Photo</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Faculty</th>
                        <th class="px-4 py-3 text-left">Department</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                    @foreach($teachers as $teacher)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <img src="{{ $teacher->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?background=random&color=fff&name=' . urlencode($teacher->full_name) }}" 
                                     alt="{{ $teacher->full_name }}" 
                                     class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700" />
                            </td>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                {{ $teacher->full_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->email ?? $teacher->secondary_email ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->department?->faculty?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $teacher->department?->name ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
