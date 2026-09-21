@props([
    'teacher',
    /* 'compact' for a directory card, 'full' for a profile page. */
    'variant' => 'compact',
])

@php
    /*
     * Says how a faculty member is engaged when it is not the ordinary way —
     * Adjunct Faculty, Visiting Faculty, Part Time, Contractual, Emeritus.
     * Silent for regular staff, and silent when the terms were never recorded,
     * so it only appears when it carries information.
     *
     * A badge rather than part of the title, because the designation is what the
     * person is and the terms are a separate fact about them. Folding the two
     * into one line would turn "Professor" into "Visiting Professor" everywhere
     * the name appears, and rewriting somebody's grade is not this component's
     * business.
     *
     * One component for all four themes, for the same reason x-teacher-status is
     * one: a factual claim about a person, which four copies of would drift. The
     * tone classes avoid slate and gray, which every theme's CSS remaps to its
     * own text tokens.
     */
    $label = $teacher->engagement_label;
@endphp

@if($label)
    @if($variant === 'full')
        <div {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md border border-teal-500/30 bg-teal-500/10 px-3 py-2 text-teal-700 dark:text-teal-300']) }}>
            <span class="text-[11px] font-bold uppercase tracking-wider">{{ $label }}</span>
        </div>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-sm border border-teal-500/30 bg-teal-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-teal-700 dark:text-teal-300']) }}
              title="{{ $label }}">
            {{ $label }}
        </span>
    @endif
@endif
