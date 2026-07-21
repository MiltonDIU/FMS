@php
    $score = $gapReport['completion_percentage'] ?? 100;
    $totalGaps = $gapReport['gaps_count'] ?? 0;
    $totalWarnings = $gapReport['warnings_count'] ?? 0;
    $totalInfo = $gapReport['optional_count'] ?? $gapReport['info_count'] ?? 0;
    $sectionScores = $gapReport['section_scores'] ?? [];
    $gaps = $gapReport['gaps'] ?? [];
    $teacherName = $teacher?->full_name ?? auth()->user()?->name ?? 'Faculty Member';
    $employeeId = $teacher?->employee_id ?? '--';
    $designation = $teacher?->designation?->name ?? '';
    $department = $teacher?->department?->name ?? '';
    $designationDept = trim("$designation of $department");
    $phone = $teacher?->phone ?? $teacher?->personal_phone ?? '';
    $emailStatus = ($teacher?->verification_status ?? '') === 'verified' ? 'Email Verified' : 'Email Pending';
    $updatedDate = $teacher?->updated_at ? $teacher->updated_at->format('d M Y') : date('d M Y');
    $initials = collect(explode(' ', str_replace(['Dr.', 'Prof.', 'Mr.', 'Mrs.'], '', $teacherName)))
        ->filter()->map(fn($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('');
    if (empty($initials)) $initials = 'FM';

    // All 13 Filament Tabs mapped to Gap Assessment sections
    $sections = [
        'basic'         => ['name' => 'Basic Info',             'desc' => 'Account & Employee ID', 'icon' => 'user',         'category' => 'Basic Info',             'tab' => 'Basic Info'],
        'contact'       => ['name' => 'Contact Info',           'desc' => 'Phone, Email & Addresses', 'icon' => 'phone',       'category' => 'Contact Info',           'tab' => 'Contact Info'],
        'personal'      => ['name' => 'Personal Details',      'desc' => 'DOB, Gender, Religion & Bio','icon' => 'file-text',  'category' => 'Personal Details',       'tab' => 'Personal Details'],
        'academic_info' => ['name' => 'Academic Info',         'desc' => 'Research Interests',    'icon' => 'sparkles',     'category' => 'Academic Info',          'tab' => 'Academic Info'],
        'education'     => ['name' => 'Academic Qualification', 'desc' => 'Degrees & Institutions', 'icon' => 'academic',   'category' => 'Academic Qualification', 'tab' => 'Educations'],
        'publication'   => ['name' => 'Publications',          'desc' => 'Research & Journals',   'icon' => 'book-open',   'category' => 'Publications',           'tab' => 'Publications'],
        'experience'    => ['name' => 'Job Experience',        'desc' => 'Work & Designations',   'icon' => 'briefcase',   'category' => 'Experience Details',      'tab' => 'Job Experience'],
        'training'      => ['name' => 'Training Experience',   'desc' => 'Courses & Workshops',   'icon' => 'academic-cap','category' => 'Training Experience',   'tab' => 'Training Experience'],
        'award'         => ['name' => 'Awards & Honors',       'desc' => 'Recognitions & Awards', 'icon' => 'trophy',       'category' => 'Awards & Honors',       'tab' => 'Awards'],
        'skill'         => ['name' => 'Skills & Expertise',    'desc' => 'Technical Specialities','icon' => 'lightning',    'category' => 'Skills & Expertise',    'tab' => 'Skills'],
        'teaching'      => ['name' => 'Teaching Areas',        'desc' => 'Subjects & Courses',    'icon' => 'presentation', 'category' => 'Teaching Areas',       'tab' => 'Teaching Areas'],
        'membership'    => ['name' => 'Memberships',           'desc' => 'Professional Bodies',   'icon' => 'user-group',   'category' => 'Memberships',           'tab' => 'Memberships'],
        'social'        => ['name' => 'Social Links',          'desc' => 'Academic & Profiles',   'icon' => 'share',        'category' => 'Social Links',          'tab' => 'Social Links'],
    ];

    $sectionGaps = [];
    foreach ($sections as $secId => $sec) {
        $sectionGaps[$secId] = collect($gaps)->filter(fn($g) => ($g['category'] ?? '') === $sec['category'])->values();
    }

    // SVG icon paths
    $iconPaths = [
        'user'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
        'phone'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>',
        'file-text'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'sparkles'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L20 12l-6.857 2.286L11 21l-2.286-6.857L2 12l6.857-2.286L11 3z"/>',
        'academic'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>',
        'book-open'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'briefcase'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'academic-cap' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.605 15.12a2 2 0 00-1.022.547l-1.4 1.4a1 1 0 00.707 1.707h16.22a1 1 0 00.707-1.707l-1.4-1.4z"/>',
        'trophy'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4a5 5 0 005 5h4a5 5 0 005-5V3M5 3h14M5 3H3v4a3 3 0 003 3h.5m14.5-7h2v4a3 3 0 01-3 3h-.5M12 12v6m-4 0h8m-6 3h4"/>',
        'lightning'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
        'presentation' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12H4z"/>',
        'user-group'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'share'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 00-5.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>',
    ];

    // Dynamic theme colors matching score tier
    if ($score >= 80) {
        $ringColor = '#10B981'; // Emerald Green
        $topBorderColor = '#10B981';
        $scoreBoxBg = '#ECFDF5';
        $scoreBoxBorder = '#A7F3D0';
        $scoreBoxText = '#047857';
        $statusLabel = 'Good Standing';
    } elseif ($score >= 50) {
        $ringColor = '#4F46E5'; // Indigo Blue
        $topBorderColor = '#4F46E5';
        $scoreBoxBg = '#EEF2FF';
        $scoreBoxBorder = '#E0E7FF';
        $scoreBoxText = '#4338CA';
        $statusLabel = 'Moderate Progress';
    } else {
        $ringColor = '#EF4444'; // Rose Red
        $topBorderColor = '#EF4444';
        $scoreBoxBg = '#FFF1F2';
        $scoreBoxBorder = '#FECDD3';
        $scoreBoxText = '#E11D48';
        $statusLabel = 'Needs Attention';
    }
@endphp

<style>
    .gap-banner *{box-sizing:border-box}
    .gap-banner{font-family:Inter,ui-sans-serif,system-ui,sans-serif;margin-bottom:1.5rem}
    .gap-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .gap-mono{font-family:'JetBrains Mono',ui-monospace,monospace}

    /* Collapsed strip */
    .gap-collapsed{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .gap-collapsed-left{display:flex;align-items:center;gap:12px}
    .gap-score-box{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;border:1px solid}
    .gap-pill{display:inline-block;padding:1px 7px;border-radius:4px;font-size:9px;font-weight:700}
    .gap-pill-indigo{background:#EEF2FF;color:#4338CA;border:1px solid #E0E7FF}
    .gap-pill-rose{background:#FFF1F2;color:#E11D48;border:1px solid #FECDD3}
    .gap-pill-blue{background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE}
    .gap-pill-emerald{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0}
    .gap-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:6px;font-size:10px;font-weight:800;cursor:pointer;border:1px solid #E0E7FF;background:#EEF2FF;color:#4338CA;transition:background .15s}
    .gap-btn:hover{background:#E0E7FF}
    .gap-btn-slate{background:#F8FAFC;border-color:#E2E8F0;color:#475569}
    .gap-btn-slate:hover{background:#F1F5F9}

    /* Expanded: top profile card */
    .gap-profile-card{padding:24px;display:flex;flex-wrap:wrap;align-items:center;gap:24px}
    .gap-avatar{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#F8FAFC,#F1F5F9);border:1px solid #E2E8F0;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#1E293B;flex-shrink:0;position:relative}
    .gap-avatar-badge{position:absolute;bottom:-4px;right:-4px;width:22px;height:22px;border-radius:8px;border:2px solid #fff;display:flex;align-items:center;justify-content:center}
    .gap-info-tag{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:8px;background:#F8FAFC;border:1px solid #E2E8F0;color:#64748B;font-size:11px}
    .gap-badge-verified{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;background:#EEF2FF;color:#4F46E5;border:1px solid #E0E7FF}
    .gap-integrity{background:#F8FAFC;border-radius:16px;border:1px solid #E2E8F0;padding:16px;display:flex;align-items:center;gap:16px;flex-shrink:0;margin-left:auto}
    .gap-integrity-ring{position:relative;width:48px;height:48px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
    .gap-integrity-score{position:absolute;font-size:11px;font-weight:800;color:#1E293B}

    /* Audit checks section */
    .gap-audit-header{display:flex;align-items:center;gap:12px;padding-bottom:16px;border-bottom:1px solid #F1F5F9;margin-bottom:20px}
    .gap-audit-icon{width:40px;height:40px;border-radius:12px;background:#FFFBEB;border:1px solid #FDE68A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .gap-pending-pill{display:inline-block;padding:2px 10px;border-radius:6px;font-size:11px;font-weight:800;background:#FFF1F2;color:#E11D48;border:1px solid #FECDD3}
    .gap-info-pill-hdr{display:inline-block;padding:2px 10px;border-radius:6px;font-size:11px;font-weight:800;background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE}

    /* Section cards grid */
    .gap-sections-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
    @media(max-width:1280px){.gap-sections-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:960px){.gap-sections-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:540px){.gap-sections-grid{grid-template-columns:1fr}}

    .gap-section-card{padding:14px 16px;border-radius:12px;border:1px solid #E2E8F0;background:#fff;cursor:pointer;transition:border-color .15s,box-shadow .15s;position:relative}
    .gap-section-card:hover{border-color:#CBD5E1;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .gap-section-icon{width:34px;height:34px;border-radius:8px;background:#F8FAFC;border:1px solid #E2E8F0;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .gap-section-icon svg{width:16px;height:16px;stroke:#64748B;fill:none}
    
    .gap-badge-group{display:flex;align-items:center;gap:4px}
    .gap-missing-pill{padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;border:1px solid #FECDD3;background:#FFF1F2;color:#E11D48}
    .gap-optional-pill{padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;border:1px solid #BFDBFE;background:#EFF6FF;color:#2563EB}
    .gap-ok-pill{padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;border:1px solid #A7F3D0;background:#ECFDF5;color:#059669}
    
    .gap-score-pill-sm{display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;background:#F1F5F9;color:#475569;border:1px solid #E2E8F0}
    .gap-section-title{font-size:13px;font-weight:700;color:#1E293B;margin:0}
    .gap-section-desc{font-size:10px;color:#94A3B8;margin:3px 0 0}
    .gap-section-arrow{color:#94A3B8;font-weight:700;margin-left:auto;font-size:16px;transition:transform .15s}
    .gap-section-card:hover .gap-section-arrow{transform:translateX(2px)}

    /* Hover dropdown popover - ::before hit-bridge prevents mouse leave gap */
    .gap-popover{position:absolute;left:0;right:0;top:100%;margin-top:6px;background:#fff;border:1px solid #CBD5E1;border-radius:10px;padding:12px;box-shadow:0 10px 30px rgba(0,0,0,.12);z-index:100;transition:opacity .15s ease,transform .15s ease}
    .gap-popover::before{content:'';position:absolute;top:-12px;left:0;right:0;height:12px;background:transparent}
    .gap-popover-title{font-size:10px;font-weight:800;color:#1E293B;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between}
    .gap-popover-item{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:6px 8px;border-radius:6px;font-size:10px;margin-bottom:4px;transition:background .15s}
    .gap-popover-item-warning{background:#FFF1F2;border:1px solid #FFE4E6}
    .gap-popover-item-warning:hover{background:#FFE4E6}
    .gap-popover-item-info{background:#F0F9FF;border:1px solid #E0F2FE}
    .gap-popover-item-info:hover{background:#E0F2FE}
    .gap-popover-item:last-child{margin-bottom:0}
    .gap-popover-label{color:#334155;font-weight:500}
    .gap-popover-go{color:#4F46E5;font-weight:800;text-decoration:underline;cursor:pointer;flex-shrink:0;background:none;border:none;padding:0;font-size:10px}
    .gap-popover-go:hover{color:#3730A3}

    .gap-tag-warning{background:#E11D48;color:#fff;font-size:8px;font-weight:800;padding:1px 4px;border-radius:3px;text-transform:uppercase}
    .gap-tag-info{background:#0284C7;color:#fff;font-size:8px;font-weight:800;padding:1px 4px;border-radius:3px;text-transform:uppercase}

    /* Collapse footer */
    .gap-collapse-footer{display:flex;justify-content:flex-end;padding-top:12px}
</style>

<div class="gap-banner" x-data="{ showDiag: false, hoveredCard: null, leaveTimeout: null }">

    {{-- ═══════════ COLLAPSED COMPACT STRIP ═══════════ --}}
    <div x-show="!showDiag" x-transition class="gap-card gap-collapsed">
        <div class="gap-collapsed-left">
            <div class="gap-score-box gap-mono" style="background:{{ $scoreBoxBg }};border-color:{{ $scoreBoxBorder }};color:{{ $scoreBoxText }}">{{ $score }}%</div>
            <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="font-size:12px;font-weight:800;color:#0F172A">{{ $teacherName }}</span>
                    <span class="gap-pill gap-pill-indigo gap-mono" style="background:{{ $scoreBoxBg }};border-color:{{ $scoreBoxBorder }};color:{{ $scoreBoxText }}">Score: {{ $score }}%</span>
                    @if($totalWarnings > 0)
                        <span class="gap-pill gap-pill-rose gap-mono">⚠️ {{ $totalWarnings }} Mandatory Gaps</span>
                    @endif
                    @if($totalInfo > 0)
                        <span class="gap-pill gap-pill-blue gap-mono">ℹ️ {{ $totalInfo }} Optional</span>
                    @endif
                    @if($totalGaps === 0)
                        <span class="gap-pill gap-pill-emerald gap-mono">✓ 100% verified</span>
                    @endif
                </div>
                <p style="font-size:11px;color:#64748B;margin:2px 0 0">
                    @if($totalGaps === 0)
                        ✓ Profile integrity has been verified across all 13 sections. No shortfalls.
                    @else
                        Diagnostics are collapsed. {{ $totalWarnings }} mandatory and {{ $totalInfo }} optional items pending. Click button to inspect.
                    @endif
                </p>
            </div>
        </div>
        <button type="button" @click="showDiag = true" class="gap-btn gap-mono">
            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            SHOW METRICS & GAPS
        </button>
    </div>

    {{-- ═══════════ EXPANDED DIAGNOSTICS ═══════════ --}}
    <div x-show="showDiag" x-collapse x-cloak>

        {{-- Row 1: Profile Card --}}
        <div class="gap-card gap-profile-card" style="border-top:4px solid {{ $topBorderColor }};margin-bottom:16px">
            {{-- Avatar --}}
            <div class="gap-avatar">
                {{ $initials }}
                <div class="gap-avatar-badge" style="background:{{ $topBorderColor }}">
                    <svg style="width:10px;height:10px" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>

            {{-- Name & Meta --}}
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <h2 style="font-size:20px;font-weight:800;color:#0F172A;margin:0;letter-spacing:-.02em">{{ $teacherName }}</h2>
                    @php
                        $vStatus = $teacher?->verification_status ?? 'unverified';
                    @endphp

                    @if($vStatus === 'verified')
                        <span class="gap-badge-verified" style="background:#ECFDF5;color:#047857;border-color:#A7F3D0">
                            <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                            Verified Faculty
                        </span>
                    @elseif($vStatus === 'pending_verification')
                        <span class="gap-badge-verified" style="background:#FFFBEB;color:#B45309;border-color:#FDE68A">
                            <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Verification Pending
                        </span>
                    @elseif($vStatus === 'correction_requested')
                        <span class="gap-badge-verified" style="background:#FFF1F2;color:#E11D48;border-color:#FECDD3">
                            ⚠️ Correction Requested
                        </span>
                    @else
                        <span class="gap-badge-verified" style="background:#F8FAFC;color:#64748B;border-color:#E2E8F0">
                            Unverified Profile
                        </span>
                    @endif
                </div>
                @if($designationDept && $designationDept !== ' of ')
                    <p style="font-size:13px;color:#64748B;margin:4px 0 0">{{ $designationDept }}</p>
                @endif
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                    <span class="gap-info-tag gap-mono">
                        <svg style="width:12px;height:12px" fill="none" stroke="#94A3B8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $emailStatus }}
                    </span>
                    @if($phone)
                        <span class="gap-info-tag gap-mono">
                            <svg style="width:12px;height:12px" fill="none" stroke="#94A3B8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ $phone }}
                        </span>
                    @endif
                    <span class="gap-info-tag gap-mono">
                        <svg style="width:12px;height:12px" fill="none" stroke="#94A3B8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Updated {{ $updatedDate }}
                    </span>
                </div>
            </div>

            {{-- Integrity Score Ring --}}
            <div class="gap-integrity">
                <div class="gap-integrity-ring">
                    <svg style="width:48px;height:48px;transform:rotate(-90deg)" viewBox="0 0 36 36">
                        <path stroke="#E2E8F0" stroke-width="3.5" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        <path stroke="{{ $ringColor }}" stroke-dasharray="{{ $score }}, 100" stroke-width="3.5" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    </svg>
                    <span class="gap-integrity-score gap-mono">{{ $score }}%</span>
                </div>
                <div>
                    <span style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#94A3B8;display:block">OVERALL INTEGRITY</span>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:14px;font-weight:800;color:#0F172A">{{ $statusLabel }}</span>
                        <span class="gap-pill gap-mono" style="background:{{ $scoreBoxBg }};border-color:{{ $scoreBoxBorder }};color:{{ $scoreBoxText }}">{{ $score }}%</span>
                    </div>
                    <span style="font-size:10px;color:#94A3B8;display:block;margin-top:1px">Compliance shortfalls detected</span>
                </div>
            </div>
        </div>

        {{-- Row 2: Gap Assessment & Integrity Checks --}}
        <div class="gap-card" style="padding:24px;margin-bottom:16px">
            <div class="gap-audit-header" style="justify-content:space-between;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="gap-audit-icon">
                        <svg style="width:18px;height:18px" fill="none" stroke="#F59E0B" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <h3 style="font-size:15px;font-weight:800;color:#0F172A;margin:0">Gap Assessment & Integrity Checks</h3>
                            @if($totalWarnings > 0)
                                <span class="gap-pending-pill gap-mono">⚠️ {{ $totalWarnings }} MANDATORY</span>
                            @endif
                            @if($totalInfo > 0)
                                <span class="gap-info-pill-hdr gap-mono">ℹ️ {{ $totalInfo }} OPTIONAL</span>
                            @endif
                        </div>
                        <p style="font-size:12px;color:#64748B;margin:3px 0 0">Mandatory fields show red warnings. Optional empty fields show blue optional badges for profile enrichment.</p>
                    </div>
                </div>

                {{-- Collapse Button placed prominently at top right --}}
                <button type="button" @click="showDiag = false" class="gap-btn gap-btn-slate gap-mono" style="margin-top:4px">
                    <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    COLLAPSE DIAGNOSTICS & AUDIT PANEL
                </button>
            </div>

            {{-- All 13 Section Cards Grid with Earned Score Breakdown --}}
            <div class="gap-sections-grid">
                @foreach($sections as $secId => $sec)
                    @php
                        $catGaps = $sectionGaps[$secId];
                        $gapsCount = $catGaps->count();
                        $warningsCount = $catGaps->where('type', 'warning')->count();
                        $optionalCount = $catGaps->whereIn('type', ['optional', 'info'])->count();
                        $firstField = $catGaps->first()['field_id'] ?? '';
                        $secScore = $sectionScores[$secId] ?? ['earned' => 0, 'weight' => 10, 'percentage' => 0];
                    @endphp

                    <div class="gap-section-card"
                          @mouseenter="clearTimeout(leaveTimeout); hoveredCard = '{{ $secId }}'"
                          @mouseleave="leaveTimeout = setTimeout(() => { hoveredCard = null }, 200)"
                          onclick="jumpToGap('{{ $firstField }}', '{{ $sec['tab'] }}', {{ $catGaps->first()['record_index'] ?? 'null' }})">

                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                            <div class="gap-section-icon">
                                <svg viewBox="0 0 24 24">{!! $iconPaths[$sec['icon']] ?? '' !!}</svg>
                            </div>
                            <div class="gap-badge-group">
                                @if($warningsCount > 0)
                                    <span class="gap-missing-pill gap-mono">⚠️ {{ $warningsCount }}</span>
                                @endif
                                @if($optionalCount > 0)
                                    <span class="gap-optional-pill gap-mono">ℹ️ {{ $optionalCount }}</span>
                                @endif
                                @if($gapsCount === 0)
                                    <span class="gap-ok-pill gap-mono">✓ OK</span>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-end;justify-content:space-between">
                            <div>
                                <h4 class="gap-section-title">{{ $sec['name'] }}</h4>
                                <p class="gap-section-desc">{{ $sec['desc'] }}</p>
                                <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                                    <span class="gap-score-pill-sm gap-mono">{{ $secScore['earned'] }}/{{ $secScore['weight'] }} pts ({{ $secScore['percentage'] }}%)</span>
                                </div>
                            </div>
                            <span class="gap-section-arrow">›</span>
                        </div>

                        {{-- Hover Popover (Smooth transition & 200ms hover retention) --}}
                        @if($gapsCount > 0)
                            <div class="gap-popover"
                                 x-show="hoveredCard === '{{ $secId }}'"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 @mouseenter="clearTimeout(leaveTimeout); hoveredCard = '{{ $secId }}'"
                                 @mouseleave="leaveTimeout = setTimeout(() => { hoveredCard = null }, 200)"
                                 x-cloak>
                                <div class="gap-popover-title">
                                    <span>Remediation Breakdown</span>
                                    <span style="color:#4F46E5">{{ $secScore['earned'] }}/{{ $secScore['weight'] }} Pts Earned</span>
                                </div>
                                @foreach($catGaps as $gapItem)
                                    <div class="gap-popover-item {{ ($gapItem['type'] ?? '') === 'warning' ? 'gap-popover-item-warning' : 'gap-popover-item-info' }}">
                                        <div style="display:flex;align-items:center;gap:6px">
                                            @if(($gapItem['type'] ?? '') === 'warning')
                                                <span class="gap-tag-warning">REQUIRED</span>
                                            @else
                                                <span class="gap-tag-info">OPTIONAL</span>
                                            @endif
                                            <span class="gap-popover-label">{{ $gapItem['label'] }}</span>
                                        </div>
                                        <button type="button"
                                                onclick="event.stopPropagation(); jumpToGap('{{ $gapItem['field_id'] }}', '{{ $sec['tab'] }}', {{ $gapItem['record_index'] ?? 'null' }})"
                                                class="gap-popover-go gap-mono">
                                            GO ›
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="gap-collapse-footer">
                <button type="button" @click="showDiag = false" class="gap-btn gap-btn-slate gap-mono">
                    <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    HIDE AUDIT PANEL
                </button>
            </div>
        </div>

    </div>
</div>
