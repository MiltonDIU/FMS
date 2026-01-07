<x-filament-widgets::widget>
    <style>
        /* Container - Use Filament's default background */
        .teacher-dashboard-overview-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            border: 1px solid #e5e7eb;
        }

        /* Dark mode adjustment */
        .dark .teacher-dashboard-overview-container {
            background: #18181B;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border-color: #374151;
        }

        /* Header Section - Now using default colors */
        .profile-header {
            padding: 2rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .dark .profile-header {
            background: #18181B;
            border-bottom-color: #374151;
        }

        .profile-avatar-wrapper {
            flex-shrink: 0;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            object-fit: cover;
            background: #f9fafb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dark .profile-avatar {
            border-color: #4b5563;
            background: #18181B;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        .profile-avatar-initial {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            background: #f9fafb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
        }

        .dark .profile-avatar-initial {
            border-color: #4b5563;
            background: #18181B;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        .profile-avatar-initial span {
            color: #4b5563;
        }

        .dark .profile-avatar-initial span {
            color: #d1d5db;
        }

        .profile-info {
            flex: 1;
            min-width: 300px;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
            color: #111827;
        }

        .dark .profile-name {
            color: #f9fafb;
        }

        .profile-designation {
            font-size: 1.1rem;
            margin-top: 0.25rem;
            font-weight: 500;
            color: #6b7280;
        }

        .dark .profile-designation {
            color: #9ca3af;
        }

        .profile-meta {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4b5563;
        }

        .dark .meta-badge {
            background: #18181B;
            border-color: #4b5563;
            color: #d1d5db;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 0;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Stats Section */
        .stats-section {
            padding: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #111827;
        }

        .dark .section-title {
            color: #f9fafb;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .dark .stat-card {
            background: #18181B;
            border-color: #4b5563;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .dark .stat-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: #111827;
        }

        .dark .stat-value {
            color: #f9fafb;
        }

        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        .dark .stat-label {
            color: #9ca3af;
        }

        /* Side Panel */
        .side-panel {
            background: #f9fafb;
            padding: 2rem;
            font-size: 0.95rem;
            border-left: 1px solid #e5e7eb;
        }

        .dark .side-panel {
            background: #18181B;
            border-left-color: #4b5563;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        .dark .info-label {
            color: #9ca3af;
        }

        .info-value {
            font-weight: 500;
            word-break: break-all;
            margin-bottom: 0.35rem;
            color: #111827;
        }

        .dark .info-value {
            color: #f3f4f6;
        }

        /* About Section */
        .about-section {
            margin-top: 2rem;
        }

        .about-content {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #4b5563;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        }

        .dark .about-content {
            background: #18181B;
            color: #d1d5db;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.2), 0 1px 2px 0 rgba(0, 0, 0, 0.1);
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .social-icon {
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .dark .social-icon {
            background: #4b5563;
            border-color: #6b7280;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .social-icon,
        .social-icon * {
            color: #4b5563 !important;
        }

        .dark .social-icon,
        .dark .social-icon * {
            color: #d1d5db !important;
        }

        .social-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .dark .social-icon:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .social-icon img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        /* Welcome Section */
        .welcome-section {
            padding: 3rem;
            text-align: center;
            color: #111827;
        }

        .dark .welcome-section {
            color: #f9fafb;
        }

        .welcome-section h2 {
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            color: #111827;
        }

        .dark .welcome-section h2 {
            color: #f9fafb;
        }

        .welcome-section p {
            font-size: 1rem;
            color: #6b7280;
        }

        .dark .welcome-section p {
            color: #9ca3af;
        }

        /* Top Level Degree Badge */
        .degree-badge {
            background: #fef3c7 !important;
            border: 1px solid #fde68a !important;
            color: #92400e !important;
        }

        .dark .degree-badge {
            background: #78350f !important;
            border-color: #92400e !important;
            color: #fbbf24 !important;
        }
    </style>

    <div class="teacher-dashboard-overview-container">
        @if($teacher)
            {{-- Header --}}
            <div class="profile-header">
                {{-- Avatar --}}
                <div class="profile-avatar-wrapper">
                    @if($teacher->photo)
                        <img src="{{ filament()->getUserAvatarUrl($teacher) }}" alt="{{ $teacher->full_name }}" class="profile-avatar">
                    @else
                        <div class="profile-avatar-initial">
                            <span>{{ substr($teacher->first_name, 0, 1) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="profile-info">
                    <h1 class="profile-name">{{ $teacher->full_name }}</h1>
                    <div class="profile-designation">
                        {{ $teacher->designation->name ?? 'Faculty Member' }} • {{ $teacher->department->name ?? 'Department' }}
                    </div>

                    <div class="profile-meta">
                        {{-- Status Badges --}}
                        @if($teacher->employmentStatus)
                            <div class="meta-badge">
                                🏢 {{ $teacher->employmentStatus->name }}
                            </div>
                        @endif

                        @if($teacher->jobType)
                            <div class="meta-badge">
                                💼 {{ $teacher->jobType->name }}
                            </div>
                        @endif

                        <div class="meta-badge">
                            📅 Joined: {{ $teacher->joining_date ? \Carbon\Carbon::parse($teacher->joining_date)->format('M d, Y') : 'N/A' }}
                        </div>

                        {{-- Reported Degree Indicator --}}
                        @php
                            $hasReportedDegree = $teacher->educations->contains(function ($education) {
                                return $education->degreeType && $education->degreeType->level && $education->degreeType->level->is_report;
                            });
                        @endphp

                        @if($hasReportedDegree)
                            <div class="meta-badge degree-badge">
                                🎓 Top Level Degree
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Main Content Grid --}}
            <div class="content-grid">
                {{-- Stats Column --}}
                <div class="stats-section">
                    <div class="section-title">
                        📊 Quick Stats
                    </div>
                    <div class="stats-cards">
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->publications_count }}</span>
                            <span class="stat-label">Publications</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->research_projects_count }}</span>
                            <span class="stat-label">Research Projects</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->awards_count }}</span>
                            <span class="stat-label">Awards</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->teaching_areas_count }}</span>
                            <span class="stat-label">Teaching Area</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->training_experiences_count }}</span>
                            <span class="stat-label">Trainings</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->certifications_count }}</span>
                            <span class="stat-label">Certifications</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->skills_count }}</span>
                            <span class="stat-label">Skills</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value">{{ $teacher->memberships_count }}</span>
                            <span class="stat-label">Memberships</span>
                        </div>
                    </div>

                    @if($teacher->bio)
                        <div class="about-section">
                            <div class="section-title">📝 About Me</div>
                            <div class="about-content">
                                {{ Str::limit(strip_tags($teacher->bio), 300) }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Side Panel (Personal & Contact) --}}
                <div class="side-panel">
                    <div class="section-title">👤 Personal Details</div>

                    <div class="info-group">
                        <div class="info-label">CONTACT</div>
                        <div class="info-value">📧 {{ $teacher->email ?? $teacher->user->email ?? 'N/A' }}</div>
                        <div class="info-value">📞 {{ $teacher->phone ?? 'N/A' }}</div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">DETAILS</div>
                        <div class="info-value">🩸 Blood Group: {{ $teacher->bloodGroup->name ?? 'N/A' }}</div>
                        <div class="info-value">☪️ Religion: {{ $teacher->religion->name ?? 'N/A' }}</div>
                        <div class="info-value">🚻 Gender: {{ $teacher->gender->name ?? 'N/A' }}</div>
                        <div class="info-value">🌍 Nationality: {{ $teacher->country?->name ?? 'N/A' }}</div>
                    </div>

                    {{-- Social Links --}}
                    @if($teacher->socialLinks->count() > 0)
                        <div class="info-group">
                            <div class="info-label">SOCIAL & WEB</div>
                            <div class="social-links">
                                @foreach($teacher->socialLinks as $link)
                                    <a href="{{ $link->url }}" target="_blank" class="social-icon" title="{{ $link->platform->name ?? 'Link' }}">
                                        @if($link->platform && $link->platform->icon)
                                            <x-icon name="{{ $link->platform->icon }}" class="w-5 h-5" />
                                        @elseif($link->platform)
                                            {{ substr($link->platform->name, 0, 1) }}
                                        @else
                                            🔗
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        @else
            <div class="welcome-section">
                <h2>Welcome!</h2>
                <p>Please setup your teacher profile to see your overview.</p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
