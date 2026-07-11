@php
    $rows = $rows ?? [];
@endphp

<div
    x-data="{ mode: 'split' }"
    class="tv-diff"
>
    <style>
        .tv-diff {
            --tv-border: #d0d7de;
            --tv-muted: #57606a;
            --tv-text: #24292f;
            --tv-bg: #ffffff;
            --tv-header: #f6f8fa;
            --tv-line: #d8dee4;
            --tv-code-border: #d0d7de;
            --tv-add-bg: #dafbe1;
            --tv-add-gutter: #aceebb;
            --tv-del-bg: #ffebe9;
            --tv-del-gutter: #ffd7d5;
        }

        .dark .tv-diff {
            --tv-border: #30363d;
            --tv-muted: #8b949e;
            --tv-text: #c9d1d9;
            --tv-bg: #0d1117;
            --tv-header: #161b22;
            --tv-line: #30363d;
            --tv-code-border: #30363d;
            --tv-add-bg: rgba(46, 160, 67, .22);
            --tv-add-gutter: rgba(46, 160, 67, .35);
            --tv-del-bg: rgba(248, 81, 73, .18);
            --tv-del-gutter: rgba(248, 81, 73, .32);
        }

        .tv-diff__file {
            background: var(--tv-bg);
            border: 1px solid var(--tv-border);
            border-radius: 6px;
            color: var(--tv-text);
            overflow: hidden;
        }

        .tv-diff__header {
            align-items: center;
            background: var(--tv-header);
            border-bottom: 1px solid var(--tv-border);
            display: flex;
            gap: 12px;
            justify-content: space-between;
            min-height: 40px;
            padding: 6px 10px;
        }

        .tv-diff__title {
            align-items: center;
            color: var(--tv-text);
            display: flex;
            font-size: 13px;
            font-weight: 600;
            gap: 8px;
            min-width: 0;
        }

        .tv-diff__meta {
            color: var(--tv-muted);
            font-size: 12px;
            font-weight: 400;
        }

        .tv-diff__switch {
            background: var(--tv-bg);
            border: 1px solid var(--tv-border);
            border-radius: 6px;
            display: inline-flex;
            flex: 0 0 auto;
            overflow: hidden;
        }

        .tv-diff__switch button {
            color: var(--tv-muted);
            font-size: 12px;
            font-weight: 600;
            line-height: 18px;
            padding: 4px 9px;
        }

        .tv-diff__switch button + button {
            border-left: 1px solid var(--tv-border);
        }

        .tv-diff__switch button[aria-pressed="true"] {
            background: #0969da;
            color: #ffffff;
        }

        .tv-diff__body {
            max-height: 520px;
            overflow: auto;
        }

        .tv-diff__split-table {
            border-collapse: collapse;
            min-width: 720px;
            table-layout: fixed;
            width: 100%;
        }

        .tv-diff__field-col {
            width: 150px;
        }

        .tv-diff__gutter-col {
            width: 30px;
        }

        .tv-diff__code-col {
            width: calc((100% - 210px) / 2);
        }

        .tv-diff__split-table thead th {
            background: var(--tv-header);
            border-bottom: 1px solid var(--tv-border);
            color: var(--tv-muted);
            font-size: 12px;
            font-weight: 600;
            height: 26px;
            padding: 0 6px;
            position: sticky;
            text-align: left;
            top: 0;
            z-index: 1;
        }

        .tv-diff__field-head,
        .tv-diff__field {
            border-right: 1px solid var(--tv-line);
            width: 150px;
        }

        .tv-diff__field {
            background: var(--tv-bg);
            color: var(--tv-muted);
            font-size: 12px;
            font-weight: 600;
            max-width: 150px;
            overflow: hidden;
            padding: 3px 6px;
            text-align: left;
            text-overflow: ellipsis;
            vertical-align: top;
            white-space: nowrap;
        }

        .tv-diff__marker,
        .tv-diff__line-marker {
            color: var(--tv-muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 17px;
            text-align: center;
            user-select: none;
            width: 30px;
        }

        .tv-diff__marker {
            border-left: 1px solid var(--tv-line);
            border-right: 1px solid var(--tv-line);
            padding: 3px 0;
            vertical-align: top;
        }

        .tv-diff__code {
            border-right: 1px solid var(--tv-line);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 17px;
            padding: 3px 6px;
            vertical-align: top;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .tv-diff__split-table tbody tr {
            border-bottom: 1px solid rgba(208, 215, 222, .55);
        }

        .dark .tv-diff__split-table tbody tr {
            border-bottom-color: rgba(48, 54, 61, .75);
        }

        .tv-diff__row--added .tv-diff__new-marker,
        .tv-diff__row--changed .tv-diff__new-marker {
            background: var(--tv-add-gutter);
            color: var(--tv-text);
        }

        .tv-diff__row--added .tv-diff__new-code,
        .tv-diff__row--changed .tv-diff__new-code {
            background: var(--tv-add-bg);
        }

        .tv-diff__row--removed .tv-diff__old-marker,
        .tv-diff__row--changed .tv-diff__old-marker {
            background: var(--tv-del-gutter);
            color: var(--tv-text);
        }

        .tv-diff__row--removed .tv-diff__old-code,
        .tv-diff__row--changed .tv-diff__old-code {
            background: var(--tv-del-bg);
        }


        .tv-diff__empty {
            color: var(--tv-muted);
            font-style: italic;
        }

        .tv-diff__unified-row {
            border-bottom: 1px solid rgba(208, 215, 222, .55);
            display: grid;
            grid-template-columns: 170px 32px minmax(0, 1fr);
            min-width: 680px;
        }

        .dark .tv-diff__unified-row {
            border-bottom-color: rgba(48, 54, 61, .75);
        }

        .tv-diff__unified-field {
            border-right: 1px solid var(--tv-line);
            color: var(--tv-muted);
            font-size: 12px;
            font-weight: 600;
            padding: 3px 6px;
            white-space: nowrap;
        }

        .tv-diff__unified-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 17px;
            padding: 3px 6px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .tv-diff__unified-row--added .tv-diff__line-marker {
            background: var(--tv-add-gutter);
            color: var(--tv-text);
        }

        .tv-diff__unified-row--added .tv-diff__unified-code {
            background: var(--tv-add-bg);
        }

        .tv-diff__unified-row--removed .tv-diff__line-marker {
            background: var(--tv-del-gutter);
            color: var(--tv-text);
        }

        .tv-diff__unified-row--removed .tv-diff__unified-code {
            background: var(--tv-del-bg);
            text-decoration: line-through;
        }

        @media (max-width: 900px) {
            .tv-diff__header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="tv-diff__file">
        <div class="tv-diff__header">
            <div class="tv-diff__title">
                <span>Teacher profile diff</span>
                <span class="tv-diff__meta">{{ count($rows) }} field{{ count($rows) === 1 ? '' : 's' }} changed</span>
            </div>

            <div class="tv-diff__switch" role="group" aria-label="Diff layout">
                <button type="button" x-on:click="mode = 'split'" x-bind:aria-pressed="mode === 'split'">Split</button>
                <button type="button" x-on:click="mode = 'unified'" x-bind:aria-pressed="mode === 'unified'">Unified</button>
            </div>
        </div>

        <div class="tv-diff__body">
            <table x-show="mode === 'split'" class="tv-diff__split-table">
                <colgroup>
                    <col class="tv-diff__field-col">
                    <col class="tv-diff__gutter-col">
                    <col class="tv-diff__code-col">
                    <col class="tv-diff__gutter-col">
                    <col class="tv-diff__code-col">
                </colgroup>
                <thead>
                    <tr>
                        <th class="tv-diff__field-head">Field</th>
                        <th colspan="2">Current data</th>
                        <th colspan="2">Proposed changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="tv-diff__row--{{ $row['status'] }}">
                            <th class="tv-diff__field">{{ $row['label'] }}</th>
                            <td class="tv-diff__marker tv-diff__old-marker">{{ $row['status'] === 'same' ? '' : '-' }}</td>
                            <td class="tv-diff__code tv-diff__old-code">@if($row['old'] !== ''){{ $row['old'] }}@else<span class="tv-diff__empty">Empty</span>@endif</td>
                            <td class="tv-diff__marker tv-diff__new-marker">{{ $row['status'] === 'same' ? '' : '+' }}</td>
                            <td class="tv-diff__code tv-diff__new-code">@if($row['new'] !== ''){{ $row['new'] }}@else<span class="tv-diff__empty">Empty</span>@endif</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tv-diff__code tv-diff__empty">No comparable data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div x-show="mode === 'unified'" x-cloak>
                @forelse($rows as $row)
                    @if($row['status'] === 'same')
                        <div class="tv-diff__unified-row">
                            <div class="tv-diff__unified-field">{{ $row['label'] }}</div>
                            <div class="tv-diff__line-marker"></div>
                            <div class="tv-diff__unified-code">{{ $row['new'] !== '' ? $row['new'] : 'Empty' }}</div>
                        </div>
                    @else
                        <div class="tv-diff__unified-row tv-diff__unified-row--removed">
                            <div class="tv-diff__unified-field">{{ $row['label'] }}</div>
                            <div class="tv-diff__line-marker">-</div>
                            <div class="tv-diff__unified-code">{{ $row['old'] !== '' ? $row['old'] : 'Empty' }}</div>
                        </div>
                        <div class="tv-diff__unified-row tv-diff__unified-row--added">
                            <div class="tv-diff__unified-field">{{ $row['label'] }}</div>
                            <div class="tv-diff__line-marker">+</div>
                            <div class="tv-diff__unified-code">{{ $row['new'] !== '' ? $row['new'] : 'Empty' }}</div>
                        </div>
                    @endif
                @empty
                    <div class="tv-diff__unified-row">
                        <div class="tv-diff__unified-code tv-diff__empty">No comparable data found.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>