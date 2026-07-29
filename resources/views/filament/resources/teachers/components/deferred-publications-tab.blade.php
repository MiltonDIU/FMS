<div
    x-data="{ publicationsLoaded: false }"
    x-effect="if (tab === 'publications') publicationsLoaded = true"
>
    <template x-if="publicationsLoaded">
        <div>
            @livewire(
                \App\Livewire\TeacherPublicationsTab::class,
                ['record' => $record, 'lazy' => true],
                key('teacher-publications-' . ($record?->getKey() ?? 'new'))
            )
        </div>
    </template>

    <div
        x-show="! publicationsLoaded"
        x-cloak
        class="flex min-h-32 items-center justify-center"
    >
        <svg class="animate-spin text-primary-600" style="width: 1.5rem; height: 1.5rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Loading publications...</span>
    </div>
</div>