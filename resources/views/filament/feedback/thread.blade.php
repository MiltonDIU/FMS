@php
    /*
     * The whole conversation, oldest first: the original message, then every
     * reply and status change. Shared by the inbox and "My Feedback"; the
     * submitter's copy leaves out the team's internal notes.
     */
    use App\Models\Feedback;
    use Illuminate\Support\Facades\Storage;

    /** @var Feedback $feedback */
    $feedback = $getRecord();
    $staffView = $staffView ?? false;

    // Queried on every render rather than read from the loaded relation, so a
    // reply sent from the page's own action shows without a reload.
    $messages = $feedback->messages()
        ->when(! $staffView, fn ($query) => $query->where('is_internal', false))
        ->get();

    $files = fn (?array $paths) => collect($paths ?? [])->filter()->map(fn (string $path) => [
        'name' => basename($path),
        'url' => Storage::disk('public')->url($path),
    ]);

    $when = fn ($time) => $time?->format('d M Y, h:i A') . ' · ' . $time?->diffForHumans();
@endphp

<div style="display: flex; flex-direction: column; gap: 0.75rem;">
    <x-filament::section compact icon="heroicon-o-user" :heading="$feedback->submitter_name" :description="$when($feedback->created_at)">
        <div style="white-space: pre-line;">{{ $feedback->message }}</div>

        @if ($feedback->page_url)
            <div style="margin-top: 0.5rem; font-size: 0.85em; opacity: 0.75;">
                Page: <x-filament::link :href="$feedback->page_url" target="_blank" size="sm">{{ \Illuminate\Support\Str::limit($feedback->page_url, 90) }}</x-filament::link>
            </div>
        @endif

        @if ($files($feedback->attachments)->isNotEmpty())
            <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                @foreach ($files($feedback->attachments) as $file)
                    <x-filament::link :href="$file['url']" target="_blank" icon="heroicon-o-paper-clip" size="sm">{{ $file['name'] }}</x-filament::link>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @foreach ($messages as $message)
        <x-filament::section
            compact
            :icon="$message->is_staff ? 'heroicon-o-lifebuoy' : 'heroicon-o-user'"
            :icon-color="$message->is_internal ? 'warning' : ($message->is_staff ? 'primary' : 'gray')"
            :heading="$message->author_name . ($message->is_staff && ! $staffView ? ' (Support team)' : '')"
            :description="$when($message->created_at)"
        >
            @if ($message->is_internal || $message->status_to)
                <x-slot name="afterHeader">
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        @if ($message->is_internal)
                            <x-filament::badge color="warning" icon="heroicon-o-lock-closed">Internal note — not shown to the user</x-filament::badge>
                        @endif

                        @if ($message->status_to)
                            <x-filament::badge :color="Feedback::statusColour($message->status_to)">
                                {{ Feedback::STATUSES[$message->status_from] ?? $message->status_from }} → {{ Feedback::STATUSES[$message->status_to] ?? $message->status_to }}
                            </x-filament::badge>
                        @endif
                    </div>
                </x-slot>
            @endif

            @if ($message->body)
                <div style="white-space: pre-line;">{{ $message->body }}</div>
            @else
                <div style="opacity: 0.6; font-style: italic;">Status changed.</div>
            @endif

            @if ($files($message->attachments)->isNotEmpty())
                <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach ($files($message->attachments) as $file)
                        <x-filament::link :href="$file['url']" target="_blank" icon="heroicon-o-paper-clip" size="sm">{{ $file['name'] }}</x-filament::link>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endforeach
</div>
