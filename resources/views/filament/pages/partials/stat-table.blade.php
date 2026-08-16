@php
    /**
     * A small bordered table of figures.
     *
     * Every table in the Scopus summary goes through here, so the borders,
     * alignment and number formatting are decided once.
     *
     * The styling is a real stylesheet rather than Tailwind classes. This panel
     * has no custom Filament theme — no tailwind.config, and vite only builds
     * the public themes — so utility classes written in a blade file are never
     * compiled into any stylesheet and silently do nothing. @once emits the
     * block a single time however many tables are on the page.
     *
     * @param  ?string  $title      heading above the table
     * @param  ?array   $headers    column headings; omit for a label/value list
     * @param  array    $rows       each row a list of cells, or [label => value]
     * @param  ?array   $footer     an emphasised final row, e.g. a total
     * @param  ?int     $labelSpan  how many leading cells are text, not numbers
     */
    $headers ??= null;
    $footer ??= null;
    $title ??= null;
    $labelSpan ??= 1;

    // A label/value list arrives associative; normalise so the markup below
    // only ever deals with lists of cells.
    $body = [];

    foreach ($rows as $key => $value) {
        $body[] = is_array($value) ? $value : [$key, $value];
    }

    $format = fn ($cell) => is_numeric($cell)
        ? number_format((float) $cell, floor((float) $cell) == $cell ? 0 : 1)
        : $cell;
@endphp

@once
    <style>
        .fms-stat { --fms-line: #e5e7eb; --fms-head: #f9fafb; --fms-muted: #4b5563; --fms-strong: #111827; }
        .dark .fms-stat { --fms-line: #374151; --fms-head: rgba(31, 41, 55, .5); --fms-muted: #9ca3af; --fms-strong: #f3f4f6; }

        .fms-stat__title { font-weight: 600; margin-bottom: .5rem; }
        .fms-stat__scroll { overflow-x: auto; }

        .fms-stat table { width: 100%; border-collapse: collapse; font-size: .875rem; }

        .fms-stat th,
        .fms-stat td { border: 1px solid var(--fms-line); padding: .5rem .75rem; vertical-align: top; }

        .fms-stat th {
            background: var(--fms-head);
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--fms-muted);
            text-align: left;
        }

        .fms-stat th.fms-num,
        .fms-stat td.fms-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 500; }

        .fms-stat td.fms-label { color: var(--fms-muted); }

        .fms-stat tr.fms-total td {
            background: var(--fms-head);
            font-weight: 600;
            color: var(--fms-strong);
            border-top-width: 2px;
        }

        .fms-swatch {
            display: inline-block;
            width: .55rem;
            height: .55rem;
            border-radius: 9999px;
            margin-right: .5rem;
            vertical-align: middle;
        }
    </style>
@endonce

<div class="fms-stat">
    @if ($title)
        <div class="fms-stat__title">{{ $title }}</div>
    @endif

    <div class="fms-stat__scroll">
        <table>
            @if ($headers)
                <thead>
                    <tr>
                        @foreach ($headers as $index => $heading)
                            <th @class(['fms-num' => $index >= $labelSpan])>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody>
                @foreach ($body as $cells)
                    <tr>
                        @foreach ($cells as $index => $cell)
                            @if ($index < $labelSpan)
                                {{--
                                    Escaped unless the caller deliberately passed
                                    an HtmlString. This partial is generic, and a
                                    label built from anything a user typed would
                                    otherwise be an injection.
                                --}}
                                <td class="fms-label">
                                    @if ($cell instanceof \Illuminate\Support\HtmlString)
                                        {!! $cell !!}
                                    @else
                                        {{ $cell }}
                                    @endif
                                </td>
                            @else
                                <td class="fms-num">{{ $format($cell) }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach

                @if ($footer)
                    <tr class="fms-total">
                        @foreach ($footer as $index => $cell)
                            <td @class(['fms-num' => $index >= $labelSpan])>{{ $format($cell) }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
