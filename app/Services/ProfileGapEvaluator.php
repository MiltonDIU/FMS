<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;

class ProfileGapEvaluator
{
    /**
     * Evaluate profile completeness and return detailed gap report for a teacher.
     *
     * Features:
     *  - Section-Weighted Academic Scoring Engine (Sum = 100%)
     *  - Per-record granular scoring (Degree 40%, Institution 40%, Passing Year 20%)
     *  - Section Score Breakdown (Earned Pts vs Max Weight Pts per tab)
     *  - Differentiates between 'warning' (Mandatory missing) and 'optional' (Optional recommended)
     *  - Dynamic Configurable Relative Peer Benchmark & Tiered Thresholds
     *  - Education Chain Hierarchy
     *
     * @param  Teacher  $teacher
     * @return array
     */
    public function evaluate(Teacher $teacher): array
    {
        $gaps = [];

        // Fetch Dynamic Admin Settings with fallbacks
        $isRelativeBenchmark = (bool) Setting::get('profile_enable_relative_benchmark', false);

        // Publications Tiers
        $pubTier1 = (int) Setting::get('profile_pub_tier1_count', 3);
        $pubTier2 = (int) Setting::get('profile_pub_tier2_count', 5);
        $pubTier3 = (int) Setting::get('profile_pub_tier3_count', 10);

        // Awards Tiers
        $awardTier1 = (int) Setting::get('profile_award_tier1_count', 1);
        $awardTier2 = (int) Setting::get('profile_award_tier2_count', 2);
        $awardTier3 = (int) Setting::get('profile_award_tier3_count', 3);

        // Training Tiers
        $trainingTier1 = (int) Setting::get('profile_training_tier1_count', 1);
        $trainingTier2 = (int) Setting::get('profile_training_tier2_count', 2);
        $trainingTier3 = (int) Setting::get('profile_training_tier3_count', 3);

        // Standard Thresholds
        $minSkills   = (int) Setting::get('profile_min_skills', 3);
        $minTeaching = (int) Setting::get('profile_min_teaching_areas', 2);
        $minSocial   = (int) Setting::get('profile_min_social_links', 2);

        // Section Weights (Total = 100%)
        $weights = [
            'basic'         => (int) Setting::get('weight_basic', 8),
            'contact'       => (int) Setting::get('weight_contact', 8),
            'personal'      => (int) Setting::get('weight_personal', 6),
            'academic_info' => (int) Setting::get('weight_academic_info', 5),
            'education'     => (int) Setting::get('weight_education', 18),
            'publication'   => (int) Setting::get('weight_publication', 15),
            'experience'    => (int) Setting::get('weight_experience', 12),
            'training'      => (int) Setting::get('weight_training', 7),
            'award'         => (int) Setting::get('weight_award', 5),
            'skill'         => (int) Setting::get('weight_skill', 6),
            'teaching'      => (int) Setting::get('weight_teaching', 5),
            'membership'    => (int) Setting::get('weight_membership', 3),
            'social'        => (int) Setting::get('weight_social', 2),
        ];

        // Track ratio achieved (0.0 to 1.0) for each section
        $sectionRatios = [];

        // Peer Benchmark max values if relative benchmark is enabled
        $maxPubsBenchmark = 10;
        $maxAwardBenchmark = 3;
        $maxTrainingBenchmark = 3;

        if ($isRelativeBenchmark) {
            $maxPubsBenchmark = Cache::remember('max_teacher_pubs_count', 1800, function () {
                return \Illuminate\Support\Facades\DB::table('publication_authors')
                    ->where('authorable_type', \App\Models\Teacher::class)
                    ->selectRaw('count(*) as aggregate')
                    ->groupBy('authorable_id')
                    ->orderByDesc('aggregate')
                    ->first()?->aggregate ?? 10;
            });

            $maxAwardBenchmark = Cache::remember('max_teacher_awards_count', 1800, function () {
                return \Illuminate\Support\Facades\DB::table('awards')
                    ->whereNull('deleted_at')
                    ->selectRaw('count(*) as aggregate')
                    ->groupBy('teacher_id')
                    ->orderByDesc('aggregate')
                    ->first()?->aggregate ?? 3;
            });

            $maxTrainingBenchmark = Cache::remember('max_teacher_trainings_count', 1800, function () {
                return \Illuminate\Support\Facades\DB::table('training_experiences')
                    ->whereNull('deleted_at')
                    ->selectRaw('count(*) as aggregate')
                    ->groupBy('teacher_id')
                    ->orderByDesc('aggregate')
                    ->first()?->aggregate ?? 3;
            });
        }

        /**
         * Helper to push gap items
         */
        $addGap = function (string $tab, string $fieldId, string $label, string $category, string $type = 'warning', ?int $recordIndex = null) use (&$gaps) {
            $gap = [
                'tab'        => $tab,
                'field_id'   => $fieldId,
                'label'      => $label,
                'category'   => $category,
                'type'       => $type === 'info' ? 'optional' : $type,
            ];
            if ($recordIndex !== null) {
                $gap['record_index'] = $recordIndex;
            }
            $gaps[] = $gap;
        };

        // ── 1. Basic Info (8% Weight) ─────────────────────────────────────────
        $hasEmpId = !empty($teacher->employee_id);
        $hasPhoto = $teacher->hasMedia('avatar') || !empty($teacher->photo);

        if (!$hasEmpId) $addGap('basic', 'employee_id', 'Employee ID is missing', 'Basic Info', 'warning');
        if (!$hasPhoto) $addGap('basic', 'photo', 'Profile Photo is empty', 'Basic Info', 'optional');

        $sectionRatios['basic'] = ($hasEmpId ? 0.6 : 0.0) + ($hasPhoto ? 0.4 : 0.0);

        // ── 2. Contact Info (8% Weight) ───────────────────────────────────────
        $hasPhone = !empty($teacher->phone);
        $hasPresAddr = !empty($teacher->present_address);
        $hasPersPhone = !empty($teacher->personal_phone);
        $hasSecEmail = !empty($teacher->secondary_email);
        $hasPermAddr = !empty($teacher->permanent_address);

        if (!$hasPhone) $addGap('contact', 'phone', 'Mobile Phone Number is missing', 'Contact Info', 'warning');
        if (!$hasPresAddr) $addGap('contact', 'present_address', 'Present Address is missing', 'Contact Info', 'warning');
        if (!$hasPersPhone) $addGap('contact', 'personal_phone', 'Personal Phone Number is empty', 'Contact Info', 'optional');
        if (!$hasSecEmail) $addGap('contact', 'secondary_email', 'Secondary Email is empty', 'Contact Info', 'optional');
        if (!$hasPermAddr) $addGap('contact', 'permanent_address', 'Permanent Address is empty', 'Contact Info', 'optional');

        $sectionRatios['contact'] = ($hasPhone ? 0.3 : 0.0) + ($hasPresAddr ? 0.3 : 0.0) + ($hasPersPhone ? 0.15 : 0.0) + ($hasSecEmail ? 0.15 : 0.0) + ($hasPermAddr ? 0.1 : 0.0);

        // ── 3. Personal Details (6% Weight) ───────────────────────────────────
        $hasDob = !empty($teacher->date_of_birth);
        $hasGender = !empty($teacher->gender_id);
        $hasBlood = !empty($teacher->blood_group_id);
        $hasReligion = !empty($teacher->religion_id);
        $hasBio = !empty($teacher->bio);

        if (!$hasDob) $addGap('personal', 'date_of_birth', 'Date of Birth is empty', 'Personal Details', 'optional');
        if (!$hasGender) $addGap('personal', 'gender_id', 'Gender is empty', 'Personal Details', 'optional');
        if (!$hasBlood) $addGap('personal', 'blood_group_id', 'Blood Group is empty', 'Personal Details', 'optional');
        if (!$hasReligion) $addGap('personal', 'religion_id', 'Religion is empty', 'Personal Details', 'optional');
        if (!$hasBio) $addGap('personal', 'bio', 'Biography / Summary is empty', 'Personal Details', 'optional');

        $sectionRatios['personal'] = (($hasDob ? 1 : 0) + ($hasGender ? 1 : 0) + ($hasBlood ? 1 : 0) + ($hasReligion ? 1 : 0) + ($hasBio ? 1 : 0)) / 5.0;

        // ── 4. Academic Info (5% Weight) ──────────────────────────────────────
        $hasResearch = !empty($teacher->research_interest);
        if (!$hasResearch) $addGap('academic_info', 'research_interest', 'Research Interest is empty', 'Academic Info', 'optional');

        $sectionRatios['academic_info'] = $hasResearch ? 1.0 : 0.0;

        // ── 5. Academic Qualification (18% Weight) ────────────────────────────
        $educations = $teacher->relationLoaded('educations')
            ? $teacher->educations
            : $teacher->educations()->with(['degreeType.level', 'educationalInstitution'])->get();

        if ($educations->isNotEmpty()) {
            $totalEduRecords = $educations->count();
            $totalEarnedRatio = 0.0;

            foreach ($educations as $index => $edu) {
                $hasDegree = !empty($edu->degree_type_id ?? $edu->degree ?? $edu->degree_name);
                $hasInst   = !empty($edu->educational_institution_id ?? $edu->institution ?? $edu->board_university);
                $hasYear   = !empty($edu->passing_year);

                if (!$hasDegree) {
                    $addGap('education', 'degree_type_id', 'Degree title missing in Education #' . ($index + 1), 'Academic Qualification', 'warning', $index);
                }
                if (!$hasInst) {
                    $addGap('education', 'educational_institution_id', 'Institution missing in Education #' . ($index + 1), 'Academic Qualification', 'warning', $index);
                }
                if (!$hasYear) {
                    $addGap('education', 'passing_year', 'Passing year is empty in Education #' . ($index + 1), 'Academic Qualification', 'optional', $index);
                }

                // Granular record scoring: Degree (40%) + Institution (40%) + Passing Year (20%)
                $recordScore = 0.0;
                if ($hasDegree) $recordScore += 0.4;
                if ($hasInst)   $recordScore += 0.4;
                if ($hasYear)   $recordScore += 0.2;

                $totalEarnedRatio += $recordScore;
            }

            $ratio = $totalEduRecords > 0 ? ($totalEarnedRatio / $totalEduRecords) : 0.0;

            // Degree chain validation check
            $degreeNames = $educations->map(function ($edu) {
                return strtolower(
                    $edu->degreeType?->name ??
                    $edu->degreeType?->level?->name ??
                    $edu->degree ??
                    $edu->degree_name ?? ''
                );
            })->implode(' ');

            $hasBachelor = (bool) preg_match('/(bachelor|bsc|ba|bba|llb|graduate|undergraduate|b\.s|b\.a|honours|honors)/i', $degreeNames);
            $hasMaster   = (bool) preg_match('/(master|msc|ma|mba|llm|postgraduate|m\.s|m\.a)/i', $degreeNames);
            $hasPhD      = (bool) preg_match('/(phd|doctor|ph\.d|doctorate)/i', $degreeNames);

            if (!$hasBachelor && ($hasMaster || $hasPhD)) {
                $addGap('education', 'degree_type_id', 'Pre-requisite Bachelor\'s degree record is missing', 'Academic Qualification', 'warning');
            }

            $sectionRatios['education'] = min(1.0, $ratio);
        } else {
            $addGap('education', 'educations', 'No Educational Qualification records found', 'Academic Qualification', 'warning');
            $sectionRatios['education'] = 0.0;
        }

        // ── 6. Publications (15% Weight - Tiered & Relative Benchmark) ────────
        $publications = $teacher->relationLoaded('publications') ? $teacher->publications : $teacher->publications()->get();
        $pubCount = $publications->count();

        $pubRes = $this->evalTieredOrRelative(
            $pubCount,
            $isRelativeBenchmark,
            $maxPubsBenchmark,
            $pubTier1,
            $pubTier2,
            $pubTier3,
            'Publication(s)'
        );

        $sectionRatios['publication'] = $pubRes['ratio'];
        if ($pubRes['ratio'] < 1.0) {
            $addGap('publication', 'publications', $pubRes['label'], 'Publications', 'optional');
        }

        foreach ($publications as $index => $pub) {
            if (empty($pub->title)) {
                $addGap('publication', 'title', 'Title missing in Publication #' . ($index + 1), 'Publications', 'warning', $index);
            }
        }

        // ── 7. Job Experience (12% Weight) ────────────────────────────────────
        $experiences = $teacher->relationLoaded('jobExperiences') ? $teacher->jobExperiences : $teacher->jobExperiences()->get();
        if ($experiences->isNotEmpty()) {
            $totalExpRecords = $experiences->count();
            $totalEarnedRatio = 0.0;

            foreach ($experiences as $index => $exp) {
                $hasOrg = !empty($exp->organization_id ?? $exp->organization ?? $exp->company_name);
                $hasPos = !empty($exp->position_id ?? $exp->position ?? $exp->designation);

                if (!$hasOrg) $addGap('experience', 'organization_id', 'Organization missing in Job Experience #' . ($index + 1), 'Experience Details', 'warning', $index);
                if (!$hasPos) $addGap('experience', 'position_id', 'Position missing in Job Experience #' . ($index + 1), 'Experience Details', 'warning', $index);

                $recordScore = 0.0;
                if ($hasOrg) $recordScore += 0.5;
                if ($hasPos) $recordScore += 0.5;

                $totalEarnedRatio += $recordScore;
            }
            $sectionRatios['experience'] = $totalExpRecords > 0 ? ($totalEarnedRatio / $totalExpRecords) : 0.0;
        } else {
            $addGap('experience', 'jobExperiences', 'No Job Experience records found', 'Experience Details', 'warning');
            $sectionRatios['experience'] = 0.0;
        }

        // ── 8. Training Experience (7% Weight - Tiered & Relative Benchmark) ──
        $trainings = $teacher->relationLoaded('trainingExperiences') ? $teacher->trainingExperiences : $teacher->trainingExperiences()->get();
        $trCount = $trainings->count();

        $trRes = $this->evalTieredOrRelative(
            $trCount,
            $isRelativeBenchmark,
            $maxTrainingBenchmark,
            $trainingTier1,
            $trainingTier2,
            $trainingTier3,
            'Training Experience(s)'
        );

        $sectionRatios['training'] = $trRes['ratio'];
        if ($trRes['ratio'] < 1.0) {
            $addGap('training', 'trainingExperiences', $trRes['label'], 'Training Experience', 'optional');
        }

        // ── 9. Awards & Honors (5% Weight - Tiered & Relative Benchmark) ──────
        $awards = $teacher->relationLoaded('awards') ? $teacher->awards : $teacher->awards()->get();
        $awardCount = $awards->count();

        $awardRes = $this->evalTieredOrRelative(
            $awardCount,
            $isRelativeBenchmark,
            $maxAwardBenchmark,
            $awardTier1,
            $awardTier2,
            $awardTier3,
            'Award(s)'
        );

        $sectionRatios['award'] = $awardRes['ratio'];
        if ($awardRes['ratio'] < 1.0) {
            $addGap('award', 'awards', $awardRes['label'], 'Awards & Honors', 'optional');
        }

        // ── 10. Skills & Expertise (6% Weight - Standard Threshold) ───────────
        $skills = $teacher->relationLoaded('skills') ? $teacher->skills : $teacher->skills()->get();
        $skillCount = $skills->count();

        if ($skillCount === 0) {
            $addGap('skill', 'skills', "No Skills recorded (Add {$minSkills}+ for full credit)", 'Skills & Expertise', 'optional');
            $sectionRatios['skill'] = 0.0;
        } elseif ($skillCount < $minSkills) {
            $addGap('skill', 'skills', "{$skillCount} Skill(s) recorded (Add {$minSkills}+ for full credit)", 'Skills & Expertise', 'optional');
            $sectionRatios['skill'] = min(0.9, $skillCount / $minSkills);
        } else {
            $sectionRatios['skill'] = 1.0;
        }

        // ── 11. Teaching Areas (5% Weight - Standard Threshold) ───────────────
        $teachingAreas = $teacher->relationLoaded('teachingAreas') ? $teacher->teachingAreas : $teacher->teachingAreas()->get();
        $taCount = $teachingAreas->count();

        if ($taCount === 0) {
            $addGap('teaching', 'teachingAreas', "No Teaching Areas recorded (Add {$minTeaching}+ for full credit)", 'Teaching Areas', 'optional');
            $sectionRatios['teaching'] = 0.0;
        } elseif ($taCount < $minTeaching) {
            $addGap('teaching', 'teachingAreas', "{$taCount} Teaching Area(s) recorded (Add {$minTeaching}+ for full credit)", 'Teaching Areas', 'optional');
            $sectionRatios['teaching'] = min(0.9, $taCount / $minTeaching);
        } else {
            $sectionRatios['teaching'] = 1.0;
        }

        // ── 12. Memberships (3% Weight) ────────────────────────────────────────
        $memberships = $teacher->relationLoaded('memberships') ? $teacher->memberships : $teacher->memberships()->get();
        if ($memberships->isNotEmpty()) {
            $totalMemRecords = $memberships->count();
            $totalEarnedRatio = 0.0;

            foreach ($memberships as $index => $mem) {
                $hasOrg = !empty($mem->membership_organization_id ?? $mem->membership_organization ?? $mem->organization);
                if (!$hasOrg) $addGap('membership', 'membership_organization_id', 'Organization missing in Membership #' . ($index + 1), 'Memberships', 'optional', $index);
                else $totalEarnedRatio += 1.0;
            }
            $sectionRatios['membership'] = $totalMemRecords > 0 ? ($totalEarnedRatio / $totalMemRecords) : 0.0;
        } else {
            $addGap('membership', 'memberships', 'No Professional Memberships recorded', 'Memberships', 'optional');
            $sectionRatios['membership'] = 0.0;
        }

        // ── 13. Social Links (2% Weight - Standard Threshold) ─────────────────
        $socialLinks = $teacher->relationLoaded('socialLinks') ? $teacher->socialLinks : $teacher->socialLinks()->get();
        $socCount = $socialLinks->count();

        if ($socCount === 0) {
            $addGap('social', 'socialLinks', "No Social/Academic Links recorded (Add {$minSocial}+ for full credit)", 'Social Links', 'optional');
            $sectionRatios['social'] = 0.0;
        } elseif ($socCount < $minSocial) {
            $addGap('social', 'socialLinks', "{$socCount} Link(s) recorded (Add {$minSocial}+ for full credit)", 'Social Links', 'optional');
            $sectionRatios['social'] = min(0.9, $socCount / $minSocial);
        } else {
            $sectionRatios['social'] = 1.0;
        }

        // ── Section-Weighted Final Completion Percentage Calculation ─────────
        $totalAchievedPoints = 0.0;
        $totalPossiblePoints = 0.0;
        $sectionScores = [];

        foreach ($weights as $secKey => $secWeight) {
            $ratio = $sectionRatios[$secKey] ?? 0.0;
            $earned = round($ratio * $secWeight, 1);
            $totalAchievedPoints += $earned;
            $totalPossiblePoints += $secWeight;

            $sectionScores[$secKey] = [
                'earned'     => $earned,
                'weight'     => $secWeight,
                'percentage' => (int) round($ratio * 100),
            ];
        }

        $completionPercentage = $totalPossiblePoints > 0 ? (int) round(($totalAchievedPoints / $totalPossiblePoints) * 100) : 100;

        $warningsCount = collect($gaps)->filter(fn($g) => $g['type'] === 'warning')->count();
        $optionalCount = collect($gaps)->filter(fn($g) => $g['type'] === 'optional' || $g['type'] === 'info')->count();

        // Group gaps by category
        $groupedGaps = [];
        foreach ($gaps as $gap) {
            $cat = $gap['category'] ?? 'General';
            if (!isset($groupedGaps[$cat])) {
                $groupedGaps[$cat] = [
                    'category'       => $cat,
                    'tab'            => $gap['tab'] ?? '',
                    'count'          => 0,
                    'warnings_count' => 0,
                    'optional_count' => 0,
                    'items'          => [],
                ];
            }
            $groupedGaps[$cat]['count']++;
            if ($gap['type'] === 'warning') {
                $groupedGaps[$cat]['warnings_count']++;
            } else {
                $groupedGaps[$cat]['optional_count']++;
            }
            $groupedGaps[$cat]['items'][] = $gap;
        }

        return [
            'completion_percentage' => $completionPercentage,
            'total_checks'          => count($weights),
            'passed_checks'         => count(array_filter($sectionRatios, fn($r) => $r >= 0.9)),
            'gaps_count'            => count($gaps),
            'warnings_count'        => $warningsCount,
            'optional_count'        => $optionalCount,
            'info_count'            => $optionalCount,
            'gaps'                  => $gaps,
            'grouped_gaps'          => $groupedGaps,
            'section_scores'        => $sectionScores,
        ];
    }

    /**
     * Helper to evaluate Tiered or Relative Peer Benchmark ratio (0.0 to 1.0) and gap label.
     */
    private function evalTieredOrRelative(
        int $actualCount,
        bool $isRelativeEnabled,
        int $maxBenchmark,
        int $tier1Count,
        int $tier2Count,
        int $tier3Count,
        string $unitLabel
    ): array {
        if ($isRelativeEnabled) {
            // Dynamic Relative Peer Benchmark Mode
            $targetBenchmark = max($maxBenchmark, $tier3Count);
            if ($actualCount === 0) {
                return [
                    'ratio' => 0.0,
                    'label' => "No {$unitLabel} recorded (Peer benchmark top: {$targetBenchmark})",
                ];
            }
            if ($actualCount >= $targetBenchmark) {
                return [
                    'ratio' => 1.0,
                    'label' => "{$unitLabel} ({$actualCount} recorded - Top Peer Benchmark)",
                ];
            }
            $ratio = min(1.0, $actualCount / $targetBenchmark);
            $ratioPercent = (int) round($ratio * 100);
            return [
                'ratio' => $ratio,
                'label' => "{$actualCount} {$unitLabel} recorded ({$ratioPercent}% of top peer benchmark: {$targetBenchmark})",
            ];
        }

        // Absolute Tiered Mode (Tier 1 = 50%, Tier 2 = 80%, Tier 3 = 100%)
        if ($actualCount === 0) {
            return [
                'ratio' => 0.0,
                'label' => "No {$unitLabel} recorded (Add {$tier1Count}+ for 50% credit)",
            ];
        }
        if ($actualCount >= $tier3Count) {
            return [
                'ratio' => 1.0,
                'label' => "{$unitLabel} ({$actualCount} recorded - 100% Full Credit)",
            ];
        }
        if ($actualCount >= $tier2Count) {
            return [
                'ratio' => 0.8,
                'label' => "{$actualCount} {$unitLabel} recorded (80% Credit achieved - Add {$tier3Count}+ for 100%)",
            ];
        }
        if ($actualCount >= $tier1Count) {
            return [
                'ratio' => 0.5,
                'label' => "{$actualCount} {$unitLabel} recorded (50% Credit achieved - Add {$tier2Count}+ for 80%)",
            ];
        }

        return [
            'ratio' => min(0.4, $actualCount / $tier1Count),
            'label' => "{$actualCount} {$unitLabel} recorded (Add {$tier1Count}+ for Tier 1 credit)",
        ];
    }
}
