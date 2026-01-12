<x-filament::widget>
    <x-filament::section
        collapsible
        :heading="'Installed Packages'"
        :description="'Total: ' . count($packages)"
    >
        {{-- <div class="flex items-center justify-between mb-4"> (Heading moved to section prop) --}}

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th scope="col" class="px-6 py-3 text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-200">Package Name</th>
                    <th scope="col" class="px-6 py-3 text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-200">Version</th>
                    @if ($check_updates)
                        <th scope="col" class="px-6 py-3 text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-200">Latest</th>
                    @endif
                    <th scope="col" class="px-6 py-3 text-xs font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-200">Reference</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                @foreach ($packages as $package)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ $package['name'] }}
                        </td>
                        <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $package['version'] }}
                                </span>
                        </td>
                        @if ($check_updates)
                            <td class="px-6 py-4">
                                @if ($package['update_available'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                        v{{ $package['latest'] }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                        Latest
                                    </span>
                                @endif
                            </td>
                        @endif
                        <td class="px-6 py-4">
                            <code class="text-xs text-gray-500 bg-gray-100 dark:bg-gray-800 dark:text-gray-400 px-2 py-1 rounded">
                                {{ \Illuminate\Support\Str::limit($package['reference'] ?? '', 8) }}
                            </code>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::widget>
