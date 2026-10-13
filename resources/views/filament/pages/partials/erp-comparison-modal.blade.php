@php
    $comparison = $comparison ?? null;
    $error = $error ?? null;
    $summary = $comparison['summary'] ?? [];
    $sections = $comparison['sections'] ?? [];
    $employeeId = $comparison['employee_id'] ?? '';
    $teacher = $comparison['teacher'] ?? null;
@endphp

<div class="fms-erp-modal">
    <style>
        .fms-erp-modal {
            --fms-erp-border: #e5e7eb;
            --fms-erp-card-bg: #ffffff;
            --fms-erp-head-bg: #f9fafb;
            --fms-erp-text-main: #111827;
            --fms-erp-text-muted: #6b7280;
            --fms-erp-diff-bg: #fffbeb;
            --fms-erp-diff-border: #fcd34d;
            --fms-erp-new-bg: #eff6ff;
            --fms-erp-new-border: #93c5fd;
            --fms-erp-match-bg: #ecfdf5;
            --fms-erp-match-border: #a7f3d0;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--fms-erp-text-main);
        }

        .dark .fms-erp-modal {
            --fms-erp-border: #374151;
            --fms-erp-card-bg: #1f2937;
            --fms-erp-head-bg: #111827;
            --fms-erp-text-main: #f9fafb;
            --fms-erp-text-muted: #9ca3af;
            --fms-erp-diff-bg: #78350f22;
            --fms-erp-diff-border: #b45309;
            --fms-erp-new-bg: #1e3a8a22;
            --fms-erp-new-border: #1d4ed8;
            --fms-erp-match-bg: #064e3b22;
            --fms-erp-match-border: #047857;
        }

        .fms-erp-hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(59, 130, 246, 0.04) 100%);
            border: 1px solid var(--fms-erp-border);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .fms-erp-pill-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .fms-erp-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .fms-erp-pill-diff {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .dark .fms-erp-pill-diff {
            background-color: #78350f44;
            color: #fde68a;
            border: 1px solid #b45309;
        }

        .fms-erp-pill-new {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .dark .fms-erp-pill-new {
            background-color: #1e3a8a44;
            color: #bfdbfe;
            border: 1px solid #1d4ed8;
        }

        .fms-erp-pill-match {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .dark .fms-erp-pill-match {
            background-color: #064e3b44;
            color: #a7f3d0;
            border: 1px solid #047857;
        }

        .fms-erp-pill-gray {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .dark .fms-erp-pill-gray {
            background-color: #374151;
            color: #d1d5db;
            border: 1px solid #4b5563;
        }

        .fms-erp-card {
            border: 1px solid var(--fms-erp-border);
            border-radius: 0.75rem;
            background-color: var(--fms-erp-card-bg);
            margin-bottom: 0.85rem;
            overflow: hidden;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        }

        .fms-erp-card-head {
            background-color: var(--fms-erp-head-bg);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-bottom: 1px solid var(--fms-erp-border);
            cursor: pointer;
            user-select: none;
        }

        .fms-erp-card-title {
            font-weight: 600;
            font-size: 0.925rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .fms-erp-card-body {
            padding: 1rem;
        }

        .fms-erp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.825rem;
        }

        .fms-erp-table th,
        .fms-erp-table td {
            padding: 0.55rem 0.75rem;
            border: 1px solid var(--fms-erp-border);
            vertical-align: top;
            text-align: left;
        }

        .fms-erp-table th {
            background-color: var(--fms-erp-head-bg);
            font-weight: 600;
            color: var(--fms-erp-text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .fms-erp-row-diff {
            background-color: var(--fms-erp-diff-bg);
        }

        .fms-erp-row-new {
            background-color: var(--fms-erp-new-bg);
        }

        .fms-erp-split {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .fms-erp-panel {
            border: 1px solid var(--fms-erp-border);
            border-radius: 0.5rem;
            padding: 0.75rem;
            background-color: var(--fms-erp-head-bg);
        }

        .fms-erp-panel-title {
            font-size: 0.775rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--fms-erp-text-muted);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fms-erp-item-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .fms-erp-item {
            padding: 0.45rem 0.65rem;
            border-radius: 0.375rem;
            background-color: var(--fms-erp-card-bg);
            border: 1px solid var(--fms-erp-border);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .fms-erp-helper-box {
            background-color: var(--fms-erp-head-bg);
            border: 1px solid var(--fms-erp-border);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .fms-erp-btn-sm {
            padding: 0.3rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.375rem;
            border: 1px solid var(--fms-erp-border);
            background-color: var(--fms-erp-card-bg);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .fms-erp-btn-sm:hover {
            background-color: var(--fms-erp-head-bg);
            border-color: #9ca3af;
        }
    </style>

    @if ($error)
        <div style="padding: 1.25rem; border-radius: 0.75rem; background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <svg style="width: 1.5rem; height: 1.5rem; flex-shrink: 0; margin-top: 0.125rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <div>
                    <h4 style="font-weight: 700; font-size: 0.95rem; margin: 0 0 0.25rem 0;">Unable to Connect to ERP System</h4>
                    <p style="margin: 0; font-size: 0.85rem; line-height: 1.5;">{{ $error }}</p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #b91c1c;">
                        Please verify that the ERP/HR API credentials are correctly configured in System Settings or contact your system administrator.
                    </p>
                </div>
            </div>
        </div>
    @elseif ($comparison)
        {{-- Hero Header --}}
        <div class="fms-erp-hero">
            <div>
                <div style="font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <span>{{ $teacher?->first_name }} {{ $teacher?->last_name }}</span>
                    <span style="font-size: 0.8rem; font-weight: 500; color: var(--fms-erp-text-muted); padding: 0.15rem 0.45rem; border-radius: 0.25rem; border: 1px solid var(--fms-erp-border);">
                        ID: {{ $employeeId }}
                    </span>
                </div>
                <div style="font-size: 0.8rem; color: var(--fms-erp-text-muted); margin-top: 0.25rem;">
                    Live comparison against DIU ERP API profile records
                </div>
            </div>

            <div class="fms-erp-pill-bar">
                @if (($summary['different'] ?? 0) > 0)
                    <span class="fms-erp-pill fms-erp-pill-diff">
                        <span>●</span> {{ $summary['different'] }} Differ
                    </span>
                @endif
                @if (($summary['new'] ?? 0) > 0)
                    <span class="fms-erp-pill fms-erp-pill-new">
                        <span>●</span> {{ $summary['new'] }} New in ERP
                    </span>
                @endif
                @if (($summary['matching'] ?? 0) > 0)
                    <span class="fms-erp-pill fms-erp-pill-match">
                        <span>✓</span> {{ $summary['matching'] }} Identical
                    </span>
                @endif
                <span class="fms-erp-pill fms-erp-pill-gray">
                    {{ $summary['total'] ?? 0 }} Sections Checked
                </span>
            </div>
        </div>

        {{-- Helper / Quick Controls --}}
        <div class="fms-erp-helper-box">
            <div style="font-size: 0.825rem; color: var(--fms-erp-text-muted);">
                💡 <strong>How to import:</strong> Review the section comparisons below. Check the sections you want to sync in the form options, then choose <strong>Merge</strong> (fills gaps safely) or <strong>Override</strong> (replaces FMS data).
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="fms-erp-btn-sm" onclick="document.querySelectorAll('.fms-erp-card-details').forEach(el => el.style.display = 'block');">
                    Expand All
                </button>
                <button type="button" class="fms-erp-btn-sm" onclick="document.querySelectorAll('.fms-erp-card-details').forEach(el => el.style.display = 'none');">
                    Collapse All
                </button>
            </div>
        </div>

        {{-- Comparison Sections --}}
        <div class="fms-erp-sections-list">
            @foreach ($sections as $secKey => $sec)
                @php
                    $isDifferent = $sec['has_differences'] ?? false;
                    $status = $sec['status'] ?? 'match';
                    $defaultOpen = $status === 'different' || $status === 'new';
                @endphp
                <div class="fms-erp-card">
                    <div class="fms-erp-card-head" onclick="var d = document.getElementById('details-{{ $secKey }}'); d.style.display = (d.style.display === 'none' ? 'block' : 'none');">
                        <div class="fms-erp-card-title">
                            <span>{{ $sec['label'] }}</span>
                            <span style="font-size: 0.75rem; font-weight: normal; color: var(--fms-erp-text-muted);">
                                ({{ $sec['description'] ?? '' }})
                            </span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            @if ($status === 'different')
                                <span class="fms-erp-pill fms-erp-pill-diff">Differences Found</span>
                            @elseif ($status === 'new')
                                <span class="fms-erp-pill fms-erp-pill-new">New in ERP</span>
                            @elseif ($status === 'fms_only')
                                <span class="fms-erp-pill fms-erp-pill-gray">FMS Only</span>
                            @else
                                <span class="fms-erp-pill fms-erp-pill-match">In Sync</span>
                            @endif

                            <span style="font-size: 0.75rem; color: var(--fms-erp-text-muted);">
                                FMS: {{ $sec['fms_count'] ?? 0 }} | ERP: {{ $sec['erp_count'] ?? 0 }}
                            </span>

                            <svg style="width: 1rem; height: 1rem; color: var(--fms-erp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>

                    <div id="details-{{ $secKey }}" class="fms-erp-card-details fms-erp-card-body" style="display: {{ $defaultOpen ? 'block' : 'none' }};">
                        @if ($sec['type'] === 'scalar')
                            <table class="fms-erp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 28%;">Field</th>
                                        <th style="width: 33%;">Current FMS Value</th>
                                        <th style="width: 33%;">Live ERP Value</th>
                                        <th style="width: 6%; text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sec['fields'] ?? [] as $field)
                                        <tr class="{{ $field['is_different'] ? 'fms-erp-row-diff' : '' }}">
                                            <td style="font-weight: 500;">{{ $field['label'] }}</td>
                                            <td>
                                                <span style="{{ blank($field['raw_fms']) ? 'color: var(--fms-erp-text-muted); font-style: italic;' : '' }}">
                                                    {{ $field['fms_value'] }}
                                                </span>
                                            </td>
                                            <td>
                                                <span style="{{ blank($field['raw_erp']) ? 'color: var(--fms-erp-text-muted); font-style: italic;' : ($field['is_different'] ? 'font-weight: 600;' : '') }}">
                                                    {{ $field['erp_value'] }}
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                @if ($field['status'] === 'different')
                                                    <span style="color: #d97706; font-size: 0.75rem; font-weight: 700;">Diff</span>
                                                @elseif ($field['status'] === 'new_in_erp')
                                                    <span style="color: #2563eb; font-size: 0.75rem; font-weight: 700;">New</span>
                                                @elseif ($field['status'] === 'fms_only')
                                                    <span style="color: #6b7280; font-size: 0.75rem;">FMS</span>
                                                @else
                                                    <span style="color: #10b981; font-size: 0.75rem;">✓</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            {{-- Relational side-by-side --}}
                            <div class="fms-erp-split">
                                <div class="fms-erp-panel">
                                    <div class="fms-erp-panel-title">
                                        <span>Current FMS</span>
                                        <span>{{ $sec['fms_count'] ?? 0 }} Record(s)</span>
                                    </div>
                                    <div class="fms-erp-item-list">
                                        @forelse ($sec['fms_items'] ?? [] as $item)
                                            <div class="fms-erp-item">{{ $item }}</div>
                                        @empty
                                            <div style="font-style: italic; color: var(--fms-erp-text-muted); font-size: 0.8rem; padding: 0.5rem 0;">
                                                No records stored in FMS.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="fms-erp-panel">
                                    <div class="fms-erp-panel-title">
                                        <span>DIU ERP System</span>
                                        <span>{{ $sec['erp_count'] ?? 0 }} Record(s)</span>
                                    </div>
                                    <div class="fms-erp-item-list">
                                        @forelse ($sec['erp_items'] ?? [] as $item)
                                            <div class="fms-erp-item" style="border-left: 3px solid #3b82f6;">
                                                {{ $item }}
                                            </div>
                                        @empty
                                            <div style="font-style: italic; color: var(--fms-erp-text-muted); font-size: 0.8rem; padding: 0.5rem 0;">
                                                No records returned by ERP.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
