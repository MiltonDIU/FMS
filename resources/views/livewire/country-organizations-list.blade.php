<div style="font-family: inherit; color: #1f2937;">
    <style>
        /* Target Livewire/Tailwind pagination icons to restrict size */
        nav[role="navigation"] svg {
            width: 16px !important;
            height: 16px !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }
        nav[role="navigation"] div {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 12px !important;
        }
        nav[role="navigation"] a,
        nav[role="navigation"] button,
        nav[role="navigation"] span[aria-current="page"] > span,
        nav[role="navigation"] span[aria-disabled="true"] > span {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 12px !important;
            margin: 0 2px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            background-color: #ffffff !important;
            color: #374151 !important;
            text-decoration: none !important;
            transition: all 0.15s !important;
            cursor: pointer !important;
        }
        nav[role="navigation"] a:hover,
        nav[role="navigation"] button:hover {
            background-color: #f9fafb !important;
            border-color: #c0c0c0 !important;
            color: #111827 !important;
        }
        nav[role="navigation"] span[aria-current="page"] > span {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
            font-weight: 600 !important;
            border-color: #d1d5db !important;
        }
        nav[role="navigation"] p {
            margin: 0 !important;
            font-size: 13px !important;
            color: #4b5563 !important;
        }
        /* Reset helper text style to not look like buttons */
        nav[role="navigation"] p span,
        nav[role="navigation"] p font {
            display: inline !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            background: none !important;
            cursor: default !important;
        }
    </style>

    <!-- Search Bar -->
    <div style="margin-bottom: 16px;">
        <input
            type="text"
            wire:model.live="search"
            placeholder="Search organizations by name..."
            style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #d1d5db; border-radius: 6px; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); color: #1f2937; background-color: #ffffff;"
        />
    </div>

    @if($organizations->isEmpty())
        <div style="text-align: center; padding: 40px; background-color: #f9fafb; border: 1px dashed #d1d5db; border-radius: 12px;">
            <p style="font-size: 14px; color: #6b7280; margin: 0;">
                {{ trim($search) !== '' ? 'No organizations match your search query.' : 'No organizations registered in this country yet.' }}
            </p>
        </div>
    @else
        <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 12px;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; background-color: #ffffff;">
                <thead>
                    <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase;">Organization Name</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; width: 180px;">Type / Role</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; width: 220px;">System Usage Counts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($organizations as $org)
                        @php
                            $types = [];
                            if ($org->is_educational_institution) $types[] = 'Education';
                            if ($org->is_employer) $types[] = 'Employer';
                            if ($org->is_training_center) $types[] = 'Training';
                            if ($org->is_professional_body) $types[] = 'Professional Body';
                            if ($org->is_awarding_body) $types[] = 'Awarding';
                            if ($org->is_certifying_authority) $types[] = 'Certifying';
                            if ($org->is_funding_agency) $types[] = 'Funding';

                            $usage = [];
                            if ($eduCount = $org->educations()->distinct('teacher_id')->count('teacher_id')) $usage['educations'] = "Educations: {$eduCount}";
                            if ($jobCount = $org->jobExperiences()->distinct('teacher_id')->count('teacher_id')) $usage['jobExperiences'] = "Jobs: {$jobCount}";
                            if ($trainCount = $org->trainingExperiences()->distinct('teacher_id')->count('teacher_id')) $usage['trainingExperiences'] = "Trainings: {$trainCount}";
                            if ($memberCount = $org->memberships()->distinct('teacher_id')->count('teacher_id')) $usage['memberships'] = "Memberships: {$memberCount}";
                            if ($awardCount = $org->awards()->distinct('teacher_id')->count('teacher_id')) $usage['awards'] = "Awards: {$awardCount}";
                            if ($certCount = $org->certifications()->distinct('teacher_id')->count('teacher_id')) $usage['certifications'] = "Certifications: {$certCount}";
                            if ($fundCount = $org->researchProjects()->distinct('teacher_id')->count('teacher_id')) $usage['researchProjects'] = "Funding: {$fundCount}";
                        @endphp
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 12px 16px; font-size: 13px; font-weight: 600; color: #111827; vertical-align: middle;">
                                {{ $org->name }}
                                @if($org->parent)
                                    <br><span style="font-size: 10px; font-weight: 400; color: #9ca3af; display: inline-flex; align-items: center; gap: 3px; margin-top: 2px;">
                                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h12M3 17h6"/></svg>
                                        Under: {{ $org->parent->name }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 12px 16px; vertical-align: middle;">
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    @foreach($types as $type)
                                        <span style="display: inline-block; padding: 2px 8px; background-color: #f3f4f6; color: #4b5563; font-size: 11px; font-weight: 500; border-radius: 4px; border: 1px solid #e5e7eb;">
                                            {{ $type }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="padding: 12px 16px; vertical-align: middle;">
                                @if(empty($usage))
                                    <span style="font-size: 12px; color: #9ca3af; font-style: italic;">Unused</span>
                                @else
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                        @foreach($usage as $relType => $text)
                                            @php
                                                $isExpanded = isset($expandedUsages["{$org->id}-{$relType}"]);
                                            @endphp
                                            <span 
                                                wire:click="toggleUsage({{ $org->id }}, '{{ $relType }}')"
                                                style="display: inline-block; padding: 2px 6px; background-color: {{ $isExpanded ? '#047857' : '#ecfdf5' }}; color: {{ $isExpanded ? '#ffffff' : '#065f46' }}; font-size: 11px; font-weight: 600; border-radius: 4px; border: 1px solid {{ $isExpanded ? '#047857' : '#a7f3d0' }}; cursor: pointer; transition: all 0.15s; user-select: none;"
                                                title="Click to view linked teachers"
                                            >
                                                {{ $text }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>

                        <!-- Expanded Teachers List row -->
                        @php
                            $expandedTypes = [];
                            foreach (['educations', 'jobExperiences', 'trainingExperiences', 'memberships', 'awards', 'certifications', 'researchProjects'] as $rel) {
                                if (isset($expandedUsages["{$org->id}-{$rel}"])) {
                                    $expandedTypes[] = $rel;
                                }
                            }
                        @endphp
                        @if(!empty($expandedTypes))
                            <tr style="background-color: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <td colspan="3" style="padding: 12px 24px;">
                                    @foreach($expandedTypes as $relType)
                                        @php
                                            $teachers = $this->getTeachersForUsage($org->id, $relType);
                                            $label = match($relType) {
                                                'educations' => 'Educations',
                                                'jobExperiences' => 'Job Experiences',
                                                'trainingExperiences' => 'Trainings',
                                                'memberships' => 'Memberships',
                                                'awards' => 'Awards',
                                                'certifications' => 'Certifications',
                                                'researchProjects' => 'Research Projects',
                                            };
                                        @endphp
                                        <div style="margin-bottom: 8px; font-size: 12px;">
                                            <strong style="color: #4b5563;">Teachers linked via {{ $label }}:</strong>
                                            @if($teachers->isEmpty())
                                                <span style="color: #9ca3af; margin-left: 8px; font-style: italic;">No active teacher records found.</span>
                                            @else
                                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; padding-left: 12px;">
                                                    @foreach($teachers as $teacher)
                                                        <a 
                                                            href="{{ \App\Filament\Resources\Teachers\TeacherResource::getUrl('edit', ['record' => $teacher->id]) }}" 
                                                            target="_blank" 
                                                            style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; background-color: #ffffff; border: 1px solid #d1d5db; color: #2563eb; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 11px; transition: all 0.15s;"
                                                        >
                                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 12px; height: 12px; flex-shrink: 0; display: inline-block;">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                            </svg>
                                                            {{ $teacher->full_name }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination Links -->
        <div style="margin-top: 16px;">
            {{ $organizations->links() }}
        </div>
    @endif
</div>
