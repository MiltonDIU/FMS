{{-- resources/views/filament/widgets/teacher-overview.blade.php --}}
<x-filament-widgets::widget>
    <style>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            transition: all 0.2s;
            background: white;
            cursor: pointer;
            width: 100%;
            color: #1e293b;
        }

        .filter-select:hover, .filter-input:hover {
            border-color: #6366f1;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
            transition: left 0.6s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
            border-color: #6366f1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* Employment Status Stats */
        .status-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
            padding: 0 1.5rem 1.5rem 1.5rem;
            background: #f8fafc;
        }

        .status-card {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .status-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .status-info {
            display: flex;
            flex-direction: column;
        }

        .status-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-count {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .content-section {
            background: white;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .teacher-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .teacher-card:hover {
            border-color: #6366f1;
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
        }

        .teacher-rank {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .rank-1 {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
        }

        .rank-2 {
            background: linear-gradient(135deg, #94a3b8, #64748b);
            color: white;
            box-shadow: 0 4px 15px rgba(148, 163, 184, 0.4);
        }

        .rank-3 {
            background: linear-gradient(135deg, #fb923c, #f97316);
            color: white;
            box-shadow: 0 4px 15px rgba(251, 146, 60, 0.4);
        }

        .rank-other {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #475569;
        }

        .teacher-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .teacher-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            flex-shrink: 0;
            overflow: hidden;
        }

        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .teacher-info {
            flex: 1;
            min-width: 0;
        }

        .teacher-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .teacher-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: #64748b;
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .teacher-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .stat-item:hover {
            transform: scale(1.05);
        }

        .stat-item-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #6366f1;
        }

        .stat-item-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .top-performers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .performer-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
        }

        .performer-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            transition: all 0.2s;
        }

        .performer-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .performer-name {
            font-weight: 500;
            color: #334155;
        }

        .performer-count {
            font-weight: bold;
            color: #6366f1;
            padding: 0.25rem 0.75rem;
            background: #ede9fe;
            border-radius: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .gap-score-ring-sm { position: relative; width: 52px; height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .gap-score-ring-sm svg { position: absolute; top: 0; left: 0; transform: rotate(-90deg); }
        .gap-score-ring-sm-label { font-size: 11px; font-weight: 800; color: #1E293B; position: relative; z-index: 1; }
        .dark .gap-score-ring-sm-label { color: #f9fafb; }

        .profile-score-bar { height: 6px; border-radius: 3px; background: #E2E8F0; overflow: hidden; margin-top: 4px; }
        .profile-score-bar-fill { height: 100%; border-radius: 3px; transition: width 0.6s ease; }

        .score-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 1px solid; }
        .score-pill-green  { background: #ECFDF5; border-color: #A7F3D0; color: #047857; }
        .score-pill-blue   { background: #EEF2FF; border-color: #E0E7FF; color: #4338CA; }
        .score-pill-red    { background: #FFF1F2; border-color: #FECDD3; color: #E11D48; }
        .score-pill-gray   { background: #F8FAFC; border-color: #E2E8F0; color: #64748B; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slideUp 0.5s ease-out; }

        /* Dark Mode Styles */
        .dark .teacher-overview-container {
            background: linear-gradient(135deg, #131315 0%, #1e40af 100%);
            border: 1px solid #374151;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        .dark .header-section {
            background: linear-gradient(135deg, #131315 0%, #1e40af 100%);
            border-bottom: 1px solid #374151;
            color: #f9fafb;
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
            border-color: #6366f1;
        }

        .dark .filter-select:focus,
        .dark .filter-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .dark .stats-grid {
            background: #18181B;
        }

        .dark .stat-card {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .stat-card:hover {
            border-color: #6366f1;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .dark .stat-card::before {
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.2), transparent);
        }

        .dark .stat-label {
            color: #9ca3af;
        }

        .dark .status-stats-grid {
            background: #18181B;
        }

        .dark .status-card {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .status-card:hover {
            border-color: #6b7280;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .dark .status-name {
            color: #9ca3af;
        }

        .dark .status-count {
            color: #f9fafb;
        }

        .dark .content-section {
            background: #18181B;
        }

        .dark .section-title {
            color: #f9fafb;
        }

        .dark .teacher-card {
            background: #141416;
            border-color: #4b5563;
        }

        .dark .teacher-card:hover {
            border-color: #6366f1;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.25);
        }

        .dark .rank-other {
            background: linear-gradient(135deg, #374151, #4b5563);
            color: #d1d5db;
        }

        .dark .teacher-name {
            color: #f9fafb;
        }

        .dark .teacher-meta {
            color: #9ca3af;
        }

        .dark .meta-badge {
            background: #18181B;
            border-color: #4b5563;
            color: #d1d5db;
        }

        .dark .teacher-stats-row {
            border-top-color: #4b5563;
        }

        .dark .stat-item {
            background: #18181B;
        }

        .dark .stat-item-value {
            color: #818cf8;
        }

        .dark .stat-item-label {
            color: #9ca3af;
        }

        .dark .top-performers-grid {
            background: #18181B;
        }

        .dark .performer-card {
            background: linear-gradient(135deg, #18181B, #18181B);
            border-color: #4b5563;
        }

        .dark .performer-title {
            color: #f9fafb;
        }

        .dark .performer-item {
            background: #141416;
        }

        .dark .performer-name {
            color: #e5e7eb;
        }

        .dark .performer-count {
            color: #818cf8;
            background: rgba(129, 140, 248, 0.1);
        }

        .dark .empty-state {
            color: #6b7280;
        }

        /* Collapse button dark mode */
        .dark .collapse-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #d1d5db;
        }
    </style>

    <div class="teacher-overview-container" x-data="{ isCollapsed: false }">
        <!-- Header -->
        <div class="header-section" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 class="text-2xl font-bold mb-2">👨‍🏫 System Overview Dashboard</h2>
                <p class="text-sm opacity-90">Comprehensive analytics and performance metrics</p>
            </div>
            <button @click="isCollapsed = !isCollapsed"
                    class="collapse-btn"
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
                <div class="filter-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">

                    {{-- Joining Date Range --}}
                    <div class="filter-group">
                        <label class="filter-label">📅 Joined From</label>
                        <input type="date" class="filter-input" wire:model.live="fromDate">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">📅 Joined To</label>
                        <input type="date" class="filter-input" wire:model.live="toDate">
                    </div>

                    {{-- Faculty --}}
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
                        <label class="filter-label">🚻 Gender</label>
                        <select class="filter-select" wire:model.live="genderFilter">
                            @foreach($genders as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">🏅 Designation</label>
                        <select class="filter-select" wire:model.live="designationFilter">
                            @foreach($designations as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Employment Status --}}
                    <div class="filter-group">
                        <label class="filter-label">🏢 Status</label>
                        <select class="filter-select" wire:model.live="employmentStatusFilter">
                            @foreach($employmentStatuses as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Job Type --}}
                    <div class="filter-group">
                        <label class="filter-label">💼 Job Type</label>
                        <select class="filter-select" wire:model.live="jobTypeFilter">
                            @foreach($jobTypes as $id => $name)
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
                            <option value="desc">Highest First</option>
                            <option value="asc">Lowest First</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value">{{ $summary['active_teachers'] }}</div>
                    <div class="stat-label">Active Teachers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-value">{{ $summary['total_publications'] }}</div>
                    <div class="stat-label">Publications</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-value">{{ $summary['total_awards'] }}</div>
                    <div class="stat-label">Awards</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📜</div>
                    <div class="stat-value">{{ $summary['total_certifications'] }}</div>
                    <div class="stat-label">Certifications</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-value">{{ $summary['total_training'] }}</div>
                    <div class="stat-label">Training</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">👔</div>
                    <div class="stat-value">{{ $summary['total_admin_roles'] }}</div>
                    <div class="stat-label">Admin Roles</div>
                </div>

                @foreach($reportedDegreeStats as $stat)
                    <div class="stat-card">
                        <div class="stat-icon">🎓</div>
                        <div class="stat-value">{{ $stat['value'] }}</div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>

            <!-- Employment Status Statistics -->
            <div class="content-section">
            <h3 class="section-title">
                🏢 Employment Status
            </h3>
            <div class="status-stats-grid">
                @foreach($statusStats as $status => $count)
                    <div class="status-card">
                        <div class="status-info">
                            <span class="status-name">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="status-count">{{ $count }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            </div>

            <div class="content-section">
                <h3 class="section-title">🌟 Top Performers</h3>
                <div class="top-performers-grid">
                    {{-- Top Profile Scorers (from cached DB column) --}}
                    <div class="performer-card" style="border:2px solid #E0E7FF">
                        <div class="performer-title" style="color:#4338CA">
                            🎯 Top Profile Scores
                            <span style="font-size:10px;font-weight:500;color:#94A3B8;margin-left:auto">from DB cache</span>
                        </div>
                        <div class="performer-list">
                            @forelse($topProfileScorers as $i => $performer)
                                @php
                                    $sc = $performer['score'];
                                    $ringClr = $sc >= 80 ? '#10B981' : ($sc >= 50 ? '#4F46E5' : '#EF4444');
                                    $pillCls = $sc >= 80 ? 'score-pill-green' : ($sc >= 50 ? 'score-pill-blue' : 'score-pill-red');
                                    $dasharray = $sc . ', 100';
                                @endphp
                                <div class="performer-item" style="gap:10px">
                                    {{-- Mini ring --}}
                                    <div class="gap-score-ring-sm">
                                        <svg width="40" height="40" viewBox="0 0 36 36">
                                            <path stroke="#E2E8F0" stroke-width="4" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                            <path stroke="{{ $ringClr }}" stroke-dasharray="{{ $dasharray }}" stroke-width="4" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                        </svg>
                                        <span class="gap-score-ring-sm-label">{{ $sc }}%</span>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div class="performer-name" style="font-size:13px">{{ $performer['name'] }}</div>
                                        <div style="font-size:10px;color:#94A3B8">{{ $performer['rank'] }}</div>
                                    </div>
                                    <span class="score-pill {{ $pillCls }}">{{ $sc }}%</span>
                                </div>
                            @empty
                                <div class="text-center text-gray-400 py-4">No score data yet — run sync</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="performer-card">
                        <div class="performer-title">📚 Top Publishers</div>
                        <div class="performer-list">
                            @forelse($topPublishers as $performer)
                                <div class="performer-item">
                                    <span class="performer-name">{{ $performer['name'] }}</span>
                                    <span class="performer-count">{{ $performer['count'] }}</span>
                                </div>
                            @empty
                                <div class="text-center text-gray-400 py-4">No data available</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="performer-card">
                        <div class="performer-title">🏆 Top Award Winners</div>
                        <div class="performer-list">
                            @forelse($topAwardWinners as $performer)
                                <div class="performer-item">
                                    <span class="performer-name">{{ $performer['name'] }}</span>
                                    <span class="performer-count">{{ $performer['count'] }}</span>
                                </div>
                            @empty
                                <div class="text-center text-gray-400 py-4">No data available</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teacher Rankings -->
            <div class="content-section">
                <h3 class="section-title">
                    📋 Teacher Rankings
                    @if($sortBy === 'profile_score')
                        <span style="font-size:11px;font-weight:500;color:#4338CA;background:#EEF2FF;padding:2px 10px;border-radius:20px;border:1px solid #E0E7FF;margin-left:8px">🎯 Sorted by Profile Score (cached)</span>
                    @endif
                </h3>

                @forelse($teacherStats as $index => $teacher)
                    @php
                        $sc = $teacher->profile_score ?? null;
                        $ringClr  = is_null($sc) ? '#94A3B8' : ($sc >= 80 ? '#10B981' : ($sc >= 50 ? '#4F46E5' : '#EF4444'));
                        $pillCls  = is_null($sc) ? 'score-pill-gray'  : ($sc >= 80 ? 'score-pill-green' : ($sc >= 50 ? 'score-pill-blue' : 'score-pill-red'));
                        $dashArr  = is_null($sc) ? '0, 100' : ($sc . ', 100');
                        $scLabel  = is_null($sc) ? 'N/A' : $sc . '%';
                        $barWidth = $sc ?? 0;
                    @endphp
                    <div class="teacher-card animate-slide-up" style="animation-delay: {{ $index * 0.05 }}s">
                        <div class="teacher-rank {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-other' }}">
                            {{ $index + 1 }}
                        </div>

                        <div class="teacher-header">
                            <div class="teacher-avatar">
                                @if($teacher->photo)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($teacher->photo) }}" alt="Avatar" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($teacher->full_name) }}&color=7F9CF5&background=EBF4FF'">
                                @else
                                    {{ strtoupper(substr($teacher->first_name, 0, 1)) }}{{ strtoupper(substr($teacher->last_name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="teacher-info">
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <div class="teacher-name">{{ $teacher->full_name }}</div>
                                    {{-- Profile score pill on ALL cards --}}
                                    <span class="score-pill {{ $pillCls }}">🎯 {{ $scLabel }}</span>
                                </div>
                                <div class="teacher-meta">
                                    @if($teacher->employee_id)
                                        <span class="meta-badge">🆔 {{ $teacher->employee_id }}</span>
                                    @endif
                                    @if($teacher->department)
                                        <span class="meta-badge">🏛️ {{ $teacher->department->name }}</span>
                                    @endif
                                    @if($teacher->designation)
                                        <span class="meta-badge">💼 {{ $teacher->designation->name }}</span>
                                    @endif
                                    @if($teacher->joining_date)
                                        <span class="meta-badge">📅 Joined {{ $teacher->joining_date->format('M d, Y') }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Profile score ring (right side) --}}
                            <div class="gap-score-ring-sm" style="margin-left:auto;margin-right:40px">
                                <svg width="52" height="52" viewBox="0 0 36 36">
                                    <path stroke="#E2E8F0" stroke-width="3.5" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path stroke="{{ $ringClr }}" stroke-dasharray="{{ $dashArr }}" stroke-width="3.5" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <span class="gap-score-ring-sm-label">{{ $scLabel }}</span>
                            </div>
                        </div>

                        {{-- Profile score progress bar (only when sorted by profile_score) --}}
                        @if($sortBy === 'profile_score' && !is_null($sc))
                            <div style="padding: 0 0 6px 0">
                                <div style="display:flex;justify-content:space-between;font-size:10px;color:#94A3B8;margin-bottom:3px">
                                    <span>Profile Completion</span>
                                    <span style="font-weight:700;color:{{ $ringClr }}">{{ $sc }}%</span>
                                </div>
                                <div class="profile-score-bar">
                                    <div class="profile-score-bar-fill" style="width:{{ $barWidth }}%;background:{{ $ringClr }}"></div>
                                </div>
                            </div>
                        @endif

                        <div class="teacher-stats-row">
                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->publications_count }}</div>
                                <div class="stat-item-label">Publications</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->awards_count }}</div>
                                <div class="stat-item-label">Awards</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->certifications_count }}</div>
                                <div class="stat-item-label">Certifications</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->training_experiences_count }}</div>
                                <div class="stat-item-label">Training</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->memberships_count }}</div>
                                <div class="stat-item-label">Memberships</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->skills_count }}</div>
                                <div class="stat-item-label">Skills</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-item-value">{{ $teacher->teaching_areas_count }}</div>
                                <div class="stat-item-label">Teaching Areas</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h4 class="text-xl font-semibold mb-2">No Teachers Found</h4>
                        <p>Try adjusting your filters to see results</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @script
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat values on load
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach((el, index) => {
                const finalValue = parseInt(el.textContent);
                if (isNaN(finalValue)) return;

                el.textContent = '0';
                setTimeout(() => {
                    let current = 0;
                    const increment = finalValue / 30;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            el.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            el.textContent = Math.floor(current);
                        }
                    }, 50);
                }, index * 100);
            });
        });

        // Re-animate on Livewire updates
        Livewire.hook('morph.updated', () => {
            const cards = document.querySelectorAll('.teacher-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease';
                    card.style.opacity = '1';
                }, index * 50);
            });
        });
    </script>
    @endscript
</x-filament-widgets::widget>
