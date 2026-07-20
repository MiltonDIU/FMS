<?php

namespace App\Helpers;

use App\Models\Country;
use App\Models\DegreeType;
use App\Models\Major;
use App\Models\Organization;
use App\Models\Position;
use App\Models\PublicationLinkage;
use App\Models\PublicationType;
use App\Models\ResultType;
use App\Models\SocialMediaPlatform;
use Illuminate\Support\Str;

class FormPayloadResolver
{
    /**
     * Resolve and format transformed overview data into Filament form state.
     */
    public static function resolveForForm(array $overview): array
    {
        $formData = [];

        // 1. Core Teacher attributes
        if (isset($overview['Teacher']) && is_array($overview['Teacher'])) {
            foreach ($overview['Teacher'] as $k => $v) {
                if (!is_array($v) && $v !== null) {
                    $formData[$k] = $v;
                }
            }
        }

        // 2. User attributes
        if (isset($overview['User']['email'])) {
            $formData['email'] = $overview['User']['email'];
        }

        if (empty($formData['webpage']) && !empty($formData['first_name'])) {
            $formData['webpage'] = Str::slug($formData['first_name'] . ' ' . ($formData['last_name'] ?? '') . '-' . rand(100, 999));
        }

        if (empty($formData['sort_order'])) {
            $formData['sort_order'] = (\App\Models\Teacher::max('sort_order') ?? \App\Models\Teacher::count()) + 1;
        }

        $rel = $overview['Relations'] ?? [];

        // Default Bangladesh country ID
        $defaultCountryId = Country::where('slug', 'bangladesh')->first()?->id ?? Country::where('slug', 'bangladeshi')->first()?->id ?? Country::first()?->id;

        // 3. Resolve Educations
        $formData['educations'] = collect($rel['Education'] ?? [])->map(function ($item) use ($defaultCountryId) {
            $degreeTypeId = $item['degree_type_id'] ?? null;
            if (!$degreeTypeId && !empty($item['degree_type'])) {
                $degreeTypeId = DegreeType::where('name', 'LIKE', '%' . $item['degree_type'] . '%')->first()?->id;
            }
            if (!$degreeTypeId && !empty($item['degree'])) {
                $degreeTypeId = DegreeType::where('name', 'LIKE', '%' . $item['degree'] . '%')->first()?->id;
            }
            if (!$degreeTypeId) {
                $degreeTypeId = DegreeType::first()?->id;
            }

            $degreeLevelId = DegreeType::find($degreeTypeId)?->degree_level_id;

            $countryId = $item['country_id'] ?? null;
            if (!$countryId && !empty($item['country'])) {
                $countryId = Country::where('name', 'LIKE', '%' . $item['country'] . '%')->first()?->id;
            }
            if (!$countryId) {
                $countryId = $defaultCountryId;
            }

            $instRaw = $item['institution'] ?? $item['educational_institution_id'] ?? null;
            $instId = static::getOrCreateOrganization($instRaw, ['is_educational_institution' => true, 'is_active' => true], $countryId);
            $instString = is_numeric($instRaw) ? (Organization::find($instRaw)?->name ?? 'Institution') : ($instRaw ?? Organization::find($instId)?->name ?? 'Institution');

            $majorRaw = $item['major'] ?? $item['major_id'] ?? null;
            $majorString = is_numeric($majorRaw) ? (Major::find($majorRaw)?->name ?? 'Major') : ($majorRaw ?? 'Major');
            $majorId = is_numeric($majorRaw) ? (int) $majorRaw : null;
            if (!$majorId && !empty($majorString)) {
                $majorId = Major::firstOrCreate(
                    ['name' => trim($majorString)],
                    ['is_active' => true]
                )->id;
            }

            $resultTypeId = $item['result_type_id'] ?? null;
            if (!$resultTypeId) {
                if (!empty($item['cgpa'])) {
                    $resultTypeId = ResultType::where('type_name', 'CGPA')->first()?->id;
                } elseif (!empty($item['marks'])) {
                    $resultTypeId = ResultType::where('type_name', 'Percentage')->first()?->id;
                } else {
                    $resultTypeId = ResultType::where('type_name', 'Grade')->first()?->id;
                }
            }
            if (!$resultTypeId) {
                $resultTypeId = ResultType::first()?->id;
            }

            return array_merge($item, [
                '_degree_level_id' => $degreeLevelId,
                'degree_type_id' => $degreeTypeId,
                'educational_institution_id' => $instId,
                'institution' => $instString,
                'major_id' => $majorId,
                'major' => $majorString,
                'result_type_id' => $resultTypeId,
                'country_id' => $countryId,
                'passing_year' => $item['passing_year'] ?? null,
                'duration' => $item['duration'] ?? null,
                'cgpa' => $item['cgpa'] ?? null,
                'scale' => $item['scale'] ?? 4.0,
                'grade' => $item['grade'] ?? null,
            ]);
        })->toArray();

        // 4. Resolve Training Experiences
        $formData['trainingExperiences'] = collect($rel['TrainingExperience'] ?? [])->map(function ($item) use ($defaultCountryId) {
            $countryId = $item['country_id'] ?? null;
            if (!$countryId && !empty($item['country'])) {
                $countryId = Country::where('name', 'LIKE', '%' . $item['country'] . '%')->first()?->id;
            }
            if (!$countryId) {
                $countryId = $defaultCountryId;
            }

            $orgRaw = $item['organization'] ?? $item['organization_id'] ?? null;
            $orgId = static::getOrCreateOrganization($orgRaw, ['is_training_center' => true, 'is_active' => true], $countryId);
            $orgString = is_numeric($orgRaw) ? (Organization::find($orgRaw)?->name ?? 'Organization') : ($orgRaw ?? Organization::find($orgId)?->name ?? 'Organization');

            return array_merge($item, [
                'title' => $item['title'] ?? 'Training Program',
                'organization_id' => $orgId,
                'organization' => $orgString,
                'country_id' => $countryId,
                'category' => $item['category'] ?? null,
                'year' => $item['year'] ?? null,
                'completion_date' => $item['completion_date'] ?? null,
                'duration_days' => $item['duration_days'] ?? null,
                'is_online' => (bool) ($item['is_online'] ?? false),
                'description' => $item['description'] ?? null,
            ]);
        })->toArray();

        // 5. Resolve Job Experiences
        $formData['jobExperiences'] = collect($rel['JobExperience'] ?? [])->map(function ($item) use ($defaultCountryId) {
            $countryId = $item['country_id'] ?? null;
            if (!$countryId && !empty($item['country'])) {
                $countryId = Country::where('name', 'LIKE', '%' . $item['country'] . '%')->first()?->id;
            }
            if (!$countryId) {
                $countryId = $defaultCountryId;
            }

            $posRaw = $item['position'] ?? $item['position_id'] ?? null;
            $posId = static::getOrCreatePosition($posRaw);
            $posString = is_numeric($posRaw) ? (Position::find($posRaw)?->name ?? 'Position') : ($posRaw ?? Position::find($posId)?->name ?? 'Position');

            $orgRaw = $item['organization'] ?? $item['organization_id'] ?? null;
            $orgId = static::getOrCreateOrganization($orgRaw, ['is_employer' => true, 'is_active' => true], $countryId);
            $orgString = is_numeric($orgRaw) ? (Organization::find($orgRaw)?->name ?? 'Organization') : ($orgRaw ?? Organization::find($orgId)?->name ?? 'Organization');

            return array_merge($item, [
                'position_id' => $posId,
                'position' => $posString,
                'organization_id' => $orgId,
                'organization' => $orgString,
                'country_id' => $countryId,
                'department' => $item['department'] ?? null,
                'start_date' => $item['start_date'] ?? null,
                'end_date' => $item['end_date'] ?? null,
                'is_current' => (bool) ($item['is_current'] ?? false),
                'responsibilities' => $item['responsibilities'] ?? null,
            ]);
        })->toArray();

        $teacherDeptId = $formData['department_id'] ?? null;
        $teacherFacultyId = $teacherDeptId ? \App\Models\Department::find($teacherDeptId)?->faculty_id : null;

        // 6. Resolve Publications
        $formData['publications'] = collect($rel['Publication'] ?? [])->map(function ($item) use ($teacherDeptId, $teacherFacultyId) {
            $pubTypeId = $item['publication_type_id'] ?? null;
            if (!$pubTypeId && !empty($item['type'])) {
                $pubTypeId = PublicationType::where('name', 'LIKE', '%' . $item['type'] . '%')->first()?->id;
            }
            if (!$pubTypeId) {
                $pubTypeId = PublicationType::first()?->id;
            }

            $linkageId = $item['publication_linkage_id'] ?? null;
            if (!$linkageId && !empty($item['linkage'])) {
                $linkageId = PublicationLinkage::where('name', 'LIKE', '%' . $item['linkage'] . '%')->first()?->id;
            }
            if (!$linkageId) {
                $linkageId = PublicationLinkage::first()?->id;
            }

            $deptId = $item['department_id'] ?? $teacherDeptId;
            $facultyId = $item['faculty_id'] ?? ($deptId ? \App\Models\Department::find($deptId)?->faculty_id : $teacherFacultyId);

            return array_merge($item, [
                'department_id' => $deptId,
                'faculty_id' => $facultyId,
                'publication_type_id' => $pubTypeId,
                'publication_linkage_id' => $linkageId,
                'title' => $item['title'] ?? 'Publication Title',
                'journal_name' => $item['journal_name'] ?? null,
                'journal_link' => $item['journal_link'] ?? null,
                'publication_date' => $item['publication_date'] ?? null,
                'publication_year' => $item['publication_year'] ?? null,
                'abstract' => $item['abstract'] ?? null,
                'research_area' => $item['research_area'] ?? null,
                'keywords' => $item['keywords'] ?? null,
                'h_index' => $item['h_index'] ?? null,
                'citescore' => $item['citescore'] ?? null,
                'impact_factor' => $item['impact_factor'] ?? null,
                'student_involvement' => (bool) ($item['student_involvement'] ?? false),
                'is_featured' => (bool) ($item['is_featured'] ?? false),
            ]);
        })->toArray();

        // 7. Resolve Skills
        $formData['skills'] = collect($rel['Skill'] ?? [])->map(function ($item) {
            $prof = $item['proficiency'] ?? 'Intermediate';
            if (Str::contains(strtolower($prof), ['expert', 'master', 'experienced', 'advanced'])) {
                $prof = 'Expert';
            } elseif (Str::contains(strtolower($prof), ['beginner', 'basic'])) {
                $prof = 'Beginner';
            } else {
                $prof = 'Intermediate';
            }

            return [
                'name' => $item['name'] ?? 'Skill',
                'proficiency' => $prof,
            ];
        })->toArray();

        // 8. Resolve Social Links dynamically by URL domain & platform name
        $formData['socialLinks'] = collect($rel['SocialLink'] ?? [])->map(function ($item) {
            $url = $item['url'] ?? '';
            $platformName = $item['platform'] ?? '';
            $platformId = $item['social_media_platform_id'] ?? null;

            if (!$platformId) {
                if (str_contains($url, 'linkedin') || str_contains(strtolower($platformName), 'linkedin')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%linkedin%')->first()?->id;
                } elseif (str_contains($url, 'github') || str_contains(strtolower($platformName), 'github')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%github%')->first()?->id;
                } elseif (str_contains($url, 'facebook') || str_contains(strtolower($platformName), 'facebook')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%facebook%')->first()?->id;
                } elseif (str_contains($url, 'scholar') || str_contains(strtolower($platformName), 'scholar')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%google scholar%')->orWhere('name', 'LIKE', '%scholar%')->first()?->id;
                } elseif (str_contains($url, 'researchgate') || str_contains(strtolower($platformName), 'researchgate')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%researchgate%')->first()?->id;
                } elseif (str_contains($url, 'twitter') || str_contains($url, 'x.com') || str_contains(strtolower($platformName), 'twitter')) {
                    $platformId = SocialMediaPlatform::where('name', 'LIKE', '%twitter%')->first()?->id;
                }
            }

            if (!$platformId) {
                $platformId = SocialMediaPlatform::first()?->id;
            }

            return [
                'social_media_platform_id' => $platformId,
                'username' => $item['username'] ?? 'username',
                'url' => $item['url'] ?? 'https://example.com',
            ];
        })->toArray();

        // 9. Certifications
        $formData['certifications'] = collect($rel['Certification'] ?? [])->map(function ($item) {
            return [
                'title' => $item['title'] ?? 'Certification',
                'issuing_authority' => $item['issuing_authority'] ?? null,
                'credential_id' => $item['credential_id'] ?? null,
            ];
        })->toArray();

        // 10. Resolve Memberships
        $formData['memberships'] = collect($rel['Membership'] ?? [])->map(function ($item) {
            $orgRaw = $item['organization'] ?? $item['membership_organization_id'] ?? null;
            $orgId = static::getOrCreateOrganization($orgRaw, ['is_professional_body' => true, 'is_active' => true]);

            if (!$orgId) {
                // Fallback to first available professional body organization or create IEEE
                $orgId = Organization::where('is_professional_body', true)->where('is_active', true)->first()?->id;
                if (!$orgId) {
                    $orgId = Organization::create([
                        'name' => 'Professional Organization',
                        'is_professional_body' => true,
                        'is_active' => true,
                    ])->id;
                }
            }

            // Membership Type ID
            $memTypeId = $item['membership_type_id'] ?? null;
            if (!$memTypeId && !empty($item['membership_type'])) {
                $memTypeId = \App\Models\MembershipType::where('name', 'LIKE', '%' . $item['membership_type'] . '%')->first()?->id;
            }
            if (!$memTypeId) {
                $memTypeId = \App\Models\MembershipType::where('is_active', true)->first()?->id;
            }

            // Scope normalization (local, national, international)
            $scopeVal = strtolower(trim($item['scope'] ?? 'national'));
            if (!in_array($scopeVal, ['local', 'national', 'international'])) {
                $scopeVal = 'national';
            }

            // Record type normalization (membership, affiliation)
            $recordTypeVal = strtolower(trim($item['record_type'] ?? 'membership'));
            if (!in_array($recordTypeVal, ['membership', 'affiliation'])) {
                $recordTypeVal = 'membership';
            }

            return [
                'membership_organization_id' => $orgId,
                'record_type' => $recordTypeVal,
                'membership_type_id' => $memTypeId,
                'position' => $item['position'] ?? null,
                'membership_id' => $item['membership_id'] ?? null,
                'scope' => $scopeVal,
                'start_date' => $item['start_date'] ?? null,
                'end_date' => $item['end_date'] ?? null,
                'status' => $item['status'] ?? 'active',
                'url' => $item['url'] ?? null,
                'description' => $item['description'] ?? null,
            ];
        })->toArray();

        // 11. Resolve Awards
        $formData['awards'] = collect($rel['Award'] ?? [])->map(function ($item) {
            $typeVal = strtolower(trim($item['type'] ?? 'award'));
            if (!in_array($typeVal, ['award', 'scholarship', 'recognition', 'appreciation', 'other'])) {
                $typeVal = 'award';
            }

            return [
                'title' => $item['title'] ?? 'Award',
                'awarding_body' => $item['awarding_body'] ?? null,
                'type' => $typeVal,
                'date' => $item['date'] ?? null,
                'year' => $item['year'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ];
        })->toArray();

        // 12. Teaching Areas
        $formData['teachingAreas'] = $rel['TeachingArea'] ?? [];

        return $formData;
    }

    /**
     * Helper to get or create Organization ensuring required query flags (is_professional_body, is_educational_institution, etc.) and country_id are set.
     */
    protected static function getOrCreateOrganization($rawNameOrId, array $flags, ?int $countryId = null): ?int
    {
        if (empty($rawNameOrId)) {
            return null;
        }

        if ($countryId) {
            $flags['country_id'] = $countryId;
        }

        if (is_numeric($rawNameOrId)) {
            $org = Organization::find((int) $rawNameOrId);
            if ($org) {
                $needsUpdate = false;
                foreach ($flags as $k => $v) {
                    if ($org->{$k} !== $v) {
                        $org->{$k} = $v;
                        $needsUpdate = true;
                    }
                }
                if ($needsUpdate) {
                    $org->save();
                }
                return $org->id;
            }
        }

        $name = trim((string) $rawNameOrId);
        $org = Organization::where('name', $name)->first();
        if ($org) {
            $needsUpdate = false;
            foreach ($flags as $k => $v) {
                if ($org->{$k} !== $v) {
                    $org->{$k} = $v;
                    $needsUpdate = true;
                }
            }
            if ($needsUpdate) {
                $org->save();
            }
            return $org->id;
        }

        $createData = array_merge(['name' => $name, 'is_active' => true], $flags);
        return Organization::create($createData)->id;
    }

    /**
     * Helper to get or create Position ensuring is_active = true so Filament query matches.
     */
    protected static function getOrCreatePosition($rawNameOrId): ?int
    {
        if (empty($rawNameOrId)) {
            return null;
        }

        if (is_numeric($rawNameOrId)) {
            $pos = Position::find((int) $rawNameOrId);
            if ($pos) {
                if (!$pos->is_active) {
                    $pos->update(['is_active' => true]);
                }
                return $pos->id;
            }
        }

        $name = trim((string) $rawNameOrId);
        $pos = Position::where('name', $name)->first();
        if ($pos) {
            if (!$pos->is_active) {
                $pos->update(['is_active' => true]);
            }
            return $pos->id;
        }

        return Position::create(['name' => $name, 'is_active' => true])->id;
    }
}
