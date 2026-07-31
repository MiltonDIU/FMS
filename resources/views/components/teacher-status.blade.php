@props([
    'teacher',
    /* 'compact' for a directory card, 'full' for a profile page. */
    'variant' => 'compact',
])

@php
    /*
     * Says when a faculty member is not currently at their desk — on leave, on
     * study leave, on deputation. Silent for anyone working normally, so it only
     * appears when it carries information.
     *
     * One component for all four themes on purpose: this is a factual claim
     * about a person, and four copies of it would drift. The tone classes avoid
     * slate and gray, which every theme's CSS remaps to its own text tokens.
     *
     * The colour comes from employment_statuses.color, set in the admin panel.
     */
    $status = $teacher->public_status;

    $tone = match ($status['tone'] ?? 'gray') {
        'warning' => 'text-amber-700 dark:text-amber-300 bg-amber-500/10 border-amber-500/30',
        'info' => 'text-sky-700 dark:text-sky-300 bg-sky-500/10 border-sky-500/30',
        'primary' => 'text-indigo-700 dark:text-indigo-300 bg-indigo-500/10 border-indigo-500/30',
        'danger' => 'text-rose-700 dark:text-rose-300 bg-rose-500/10 border-rose-500/30',
        default => 'text-zinc-700 dark:text-zinc-300 bg-zinc-500/10 border-zinc-500/30',
    };
@endphp

@if($status)
    @if($variant === 'full')
        <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-2.5 gap-y-1 rounded-md border px-3 py-2 ' . $tone]) }}>
            <span class="text-[11px] font-bold uppercase tracking-wider">{{ $status['label'] }}</span>
            @if($status['note'])
                <span class="text-[12px] opacity-80">{{ $status['note'] }}</span>
            @endif
        </div>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ' . $tone]) }}
              title="{{ $status['note'] ?: $status['label'] }}">
            {{ $status['label'] }}
        </span>
    @endif
@endif
