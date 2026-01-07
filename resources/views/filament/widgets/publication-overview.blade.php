{{-- resources/views/filament/widgets/publication-overview.blade.php --}}
<x-filament-widgets::widget>
    <style>
        /* Light Mode */
        .pub-overview-container {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .header-section {
            backdrop-filter: blur(10px);
            background: #ffffff; /* pure white */
            /* backdrop-filter remove korlam, optional jodi opaque white hoi */
            padding: 2rem;
            color: black; /* white background e text black thik lage */
            border-bottom: 1px solid rgba(0, 0, 0, 0.2); /* subtle border */
        }

        /* Light Mode */
        .teacher-overview-container {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .header-section {
            background: #ffffff; /* pure white */
            /* backdrop-filter remove korlam, optional jodi opaque white hoi */
            padding: 2rem;
            color: black; /* white background e text black thik lage */
            border-bottom: 1px solid rgba(0, 0, 0, 0.2); /* subtle border */
        }



        .filters-section {
            background: white;
            padding: 1.5rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: #334155;
            font-size: 0.875rem;
        }

        .filter-select, .filter-input {
            padding: 0.625rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            width: 100%;
            background: white;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-select:hover, .filter-input:hover {
            border-color: #3b82f6;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: #f8fafc;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .pub-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            position: relative;
        }

        .pub-card:hover {
            border-color: #3b82f6;
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
        }

        .pub-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .pub-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.4;
        }

        .pub-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        .meta-badge {
            background: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s;
        }

        .meta-badge:hover {
            border-color: #3b82f6;
        }

        .content-section {
            background: white;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .performer-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .performer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .performer-item:hover {
            border-color: #cbd5e1;
            transform: translateX(3px);
        }

        /* Dark Mode Styles */
        .dark .pub-overview-container {
            background: linear-gradient(135deg, #131315 0%, #1e40af 100%);
            border: 1px solid #374151;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        .dark .header-section {
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .dark .filters-section {
            background: #18181B;
            border-bottom: 2px solid #374151;
        }

        .dark .filter-label {
            color: #d1d5db;
        }

        .dark .filter-select,
        .dark .filter-input {
            background: #141416;
            border-color: #4b5563;
            color: #f9fafb;
        }

        .dark .filter-select:hover,
        .dark .filter-input:hover {
            border-color: #60a5fa;
        }

        .dark .filter-select:focus,
        .dark .filter-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        .dark .stats-grid {
            background: #18181B;
        }

        .dark .stat-card {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .stat-card:hover {
            border-color: #60a5fa;
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.2);
        }

        .dark .stat-value {
            color: #60a5fa;
        }

        .dark .stat-label {
            color: #9ca3af;
        }

        .dark .content-section {
            background: #18181B;
        }

        .dark .section-title {
            color: #f9fafb;
        }

        .dark .pub-card {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .pub-card:hover {
            border-color: #60a5fa;
            box-shadow: 0 8px 25px rgba(96, 165, 250, 0.2);
        }

        .dark .pub-title {
            color: #f9fafb;
        }

        .dark .pub-meta {
            color: #9ca3af;
        }

        .dark .meta-badge {
            background: #18181B;
            border-color: #4b5563;
            color: #d1d5db;
        }

        .dark .performer-item {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .performer-item:hover {
            border-color: #6b7280;
        }

        /* Sidebar specific dark mode */
        .dark .content-section[style*="background: #f8fafc"] {
            background: #141416 !important;
            border-left-color: #4b5563 !important;
        }

        /* Text colors in sidebar */
        .dark .font-medium.text-gray-700 {
            color: #e5e7eb !important;
        }

        .dark .bg-blue-100 {
            background-color: rgba(59, 130, 246, 0.1) !important;
        }

        .dark .text-blue-600 {
            color: #60a5fa !important;
        }

        /* Empty state dark mode */
        .dark .text-gray-400 {
            color: #6b7280 !important;
        }

        .dark .text-gray-600 {
            color: #9ca3af !important;
        }

        /* Collapse button dark mode */
        .dark button[style*="background: rgba(255,255,255,0.2)"] {
            background: rgba(255, 255, 255, 0.1) !important;
        }

        /* Italic text dark mode */
        .dark .italic {
            color: #9ca3af !important;
        }
    </style>

    <div class="pub-overview-container" x-data="{ isCollapsed: false }">
        <!-- Header -->
        <div class="header-section" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 class="text-2xl font-bold mb-2">📚 Research & Publications Overview</h2>
                <p class="text-sm opacity-90">Analytics for research outputs, citations, and impact.</p>
            </div>
            <button @click="isCollapsed = !isCollapsed"
                    style="background: black; border: none; padding: 0.5rem; border-radius: 8px; cursor: pointer; color: white; transition: background 0.2s;">
                <svg x-show="!isCollapsed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                </svg>
                <svg x-show="isCollapsed" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        </div>

        <div x-show="!isCollapsed" x-collapse>
            <!-- Filters -->
            <div class="filters-section">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">📅 From</label>
                        <input type="date" class="filter-input" wire:model.live="fromDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">📅 To</label>
                        <input type="date" class="filter-input" wire:model.live="toDate">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🗓️ Year</label>
                        <select class="filter-select" wire:model.live="yearFilter">
                            @foreach($years as $year => $label)
                                <option value="{{ $year }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🏫 Faculty</label>
                        <select class="filter-select" wire:model.live="facultyFilter">
                            @foreach($faculties as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🏛️ Department</label>
                        <select class="filter-select" wire:model.live="departmentFilter">
                            @foreach($departments as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">📑 Type</label>
                        <select class="filter-select" wire:model.live="typeFilter">
                            @foreach($types as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">💰 Grant</label>
                        <select class="filter-select" wire:model.live="grantFilter">
                            @foreach($grants as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🔗 Linkage</label>
                        <select class="filter-select" wire:model.live="linkageFilter">
                            @foreach($linkages as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">📊 Quartile</label>
                        <select class="filter-select" wire:model.live="quartileFilter">
                            @foreach($quartiles as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🤝 Collaboration</label>
                        <select class="filter-select" wire:model.live="collaborationFilter">
                            @foreach($collaborations as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">📊 Sort By</label>
                        <select class="filter-select" wire:model.live="sortBy">
                            @foreach($sortOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🔄 Order</label>
                        <select class="filter-select" wire:model.live="sortDirection">
                            <option value="desc">Desc</option>
                            <option value="asc">Asc</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{{ $summary['total_publications'] }}</div>
                    <div class="stat-label">Total Publications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $summary['avg_impact_factor'] }}</div>
                    <div class="stat-label">Avg. Impact Factor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $summary['avg_citescore'] }}</div>
                    <div class="stat-label">Avg. CiteScore</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $summary['total_featured'] }}</div>
                    <div class="stat-label">Featured</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $summary['student_involvement'] }}</div>
                    <div class="stat-label">Student Involved</div>
                </div>

                <!-- Dynamic Type Stats -->
                @foreach($typeStats as $stat)
                    <div class="stat-card">
                        <div class="stat-value">{{ $stat['value'] }}</div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0;">
                <!-- Publications List -->
                <div class="content-section">
                    <h3 class="section-title">📝 Latest Publications</h3>

                    @forelse($publications as $pub)
                        <div class="pub-card">
                            <div class="pub-header">
                                <div class="pub-title">{{ $pub->title }}</div>
                            </div>
                            <div class="text-sm text-gray-600 italic mb-2">
                                {{ $pub->journal_name }} @if($pub->publication_year) ({{ $pub->publication_year }}) @endif
                            </div>

                            <div class="pub-meta">
                                @if($pub->type)
                                    <span class="meta-badge">📑 {{ $pub->type->name }}</span>
                                @endif
                                @if($pub->quartile)
                                    <span class="meta-badge">📊 {{ $pub->quartile->name }}</span>
                                @endif
                                @if($pub->impact_factor > 0)
                                    <span class="meta-badge">📈 IF: {{ $pub->impact_factor }}</span>
                                @endif
                                @if($pub->citescore > 0)
                                    <span class="meta-badge">🎯 CS: {{ $pub->citescore }}</span>
                                @endif
                                @if($pub->department)
                                    <span class="meta-badge">🏛️ {{ $pub->department->name }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-400 py-8">No publications found matching criteria.</div>
                    @endforelse
                </div>

                <!-- Top Journals Sidebar -->
                <div class="content-section" style="background: #f8fafc; border-left: 1px solid #e2e8f0;">
                    <h3 class="section-title">📰 Top Journals</h3>
                    <div class="performer-list">
                        @forelse($topJournals as $journal)
                            <div class="performer-item">
                                <span class="font-medium text-gray-700 text-sm">{{ \Illuminate\Support\Str::limit($journal['journal_name'], 25) }}</span>
                                <span class="font-bold text-blue-600 bg-blue-100 px-2 py-1 rounded-full text-xs">{{ $journal['count'] }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">No data</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
