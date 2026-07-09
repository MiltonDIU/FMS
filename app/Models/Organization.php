<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'country_id',
        'parent_id',
        'is_educational_institution',
        'is_employer',
        'is_training_center',
        'is_professional_body',
        'is_awarding_body',
        'is_certifying_authority',
        'is_funding_agency',
        'is_active',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_educational_institution' => 'boolean',
        'is_employer' => 'boolean',
        'is_training_center' => 'boolean',
        'is_professional_body' => 'boolean',
        'is_awarding_body' => 'boolean',
        'is_certifying_authority' => 'boolean',
        'is_funding_agency' => 'boolean',
    ];

    public static function detectCountryFromName(string $name): ?int
    {
        $patterns = [
            // United Kingdom (249)
            249 => [
                'UK', 'United Kingdom', 'U.K.', 'England', 'Britain', 'Great Britain',
                'London', 'Manchester', 'Cambridge', 'Oxford', 'Coventry', 'IET'
            ],
            // United States (250)
            250 => [
                'USA', 'United States', 'U.S.A.', 'America', 'US ', 'U.S. ', 'Washington',
                'California', 'Harvard', 'MIT', 'Stanford', 'IEEE', 'USAID', 'ACM'
            ],
            // Australia (13)
            13 => [
                'Australia', 'Wollongong', 'Western Sydney', 'Sydney', 'Melbourne', 'AIM'
            ],
            // India (105)
            105 => [
                'India', 'New Delhi', 'Laxmi Book', 'IIT', 'Mumbai', 'Bangalore'
            ],
            // Malaysia (136)
            136 => [
                'Malaysia', 'Kuala Lumpur', 'UM', 'USM', 'UKM'
            ],
            // Sweden (224)
            224 => [
                'Sweden', 'Linnaeus', 'Swedish', 'Stockholm', 'Gothenburg'
            ],
            // Sri Lanka (219)
            219 => [
                'Sri Lanka', 'Srilanka', 'Colombo', 'TIIKM'
            ],
            // Italy (112)
            112 => [
                'Italy', 'Sannio', 'Rome', 'Milan'
            ],
            // Japan (114)
            114 => [
                'Japan', 'Hiroshima', 'Yahata', 'JASSO', 'Tokyo', 'Osaka'
            ],
            // Germany (86)
            86 => [
                'Germany', 'Berlin', 'Munich', 'Frankfurt', 'GTZ'
            ],
            // Canada (40)
            40 => [
                'Canada', 'Toronto', 'Vancouver', 'Montreal'
            ],
            // Turkey (238)
            238 => [
                'Turkey', 'Karabuk', 'Cankiri', 'Istanbul'
            ],
            // Vietnam (256)
            256 => [
                'Vietnam', 'PHENIKAA', 'Hanoi'
            ]
        ];

        foreach ($patterns as $countryId => $keywords) {
            foreach ($keywords as $keyword) {
                // To avoid Karabuk matching UK substring:
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $name)) {
                    return $countryId;
                }
                if (stripos($name, "({$keyword})") !== false || 
                    stripos($name, ", {$keyword}") !== false
                ) {
                    return $countryId;
                }
                if ($keyword === 'UK' && (stripos($name, ' UK') !== false || stripos($name, 'UK ') !== false)) {
                    if (preg_match('/\buk\b/i', $name)) {
                        return $countryId;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Case-insensitive find or create with auto-approval logic.
     *
     * @param string $name
     * @param int|null $teacherId
     * @param int|null $countryId
     * @param array $flags
     * @return self
     */
    public static function findOrCreateWithAutoApproval(string $name, ?int $teacherId, ?int $countryId = null, array $flags = []): self
    {
        $name = trim($name);

        // Auto-detect country if null or Bangladesh (18)
        if ($countryId === null || $countryId == 18) {
            $detectedCountryId = self::detectCountryFromName($name);
            if ($detectedCountryId) {
                $countryId = $detectedCountryId;
            }
        }
        
        $existing = self::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when($countryId, function ($q) use ($countryId) {
                $q->where(function ($sub) use ($countryId) {
                    $sub->where('country_id', $countryId)
                        ->orWhereNull('country_id');
                });
            })
            ->when($countryId, function ($q) use ($countryId) {
                $q->orderByRaw('CASE WHEN country_id = ? THEN 0 ELSE 1 END', [$countryId]);
            })
            ->when(!$countryId, function ($q) {
                $q->orderByRaw('CASE WHEN country_id IS NULL THEN 0 ELSE 1 END');
            })
            ->first();

        // Check if console or admin/staff
        $isAdmin = app()->runningInConsole() || (auth()->check() && !auth()->user()->hasRole('teacher'));

        if ($existing) {
            $update = [];
            // Merge flags if they are not set yet
            foreach ($flags as $flag => $val) {
                if ($val && !$existing->$flag) {
                    $update[$flag] = true;
                }
            }
            if ($countryId && !$existing->country_id) {
                $update['country_id'] = $countryId;
            }
            if (!$existing->is_active && ($isAdmin || ($teacherId && $existing->created_by !== $teacherId))) {
                $update['is_active'] = true;
                $update['approved_by'] = auth()->check() ? auth()->id() : null;
            }
            if (!empty($update)) {
                $existing->update($update);
            }
            return $existing;
        }

        $insertData = array_merge([
            'name'        => $name,
            'country_id'  => $countryId,
            'is_active'   => $isAdmin,
            'created_by'  => $teacherId,
            'approved_by' => $isAdmin && auth()->check() ? auth()->id() : null,
        ], $flags);

        return self::create($insertData);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function educations()
    {
        return $this->hasMany(Education::class, 'educational_institution_id');
    }

    public function jobExperiences()
    {
        return $this->hasMany(JobExperience::class, 'organization_id');
    }

    public function trainingExperiences()
    {
        return $this->hasMany(TrainingExperience::class, 'organization_id');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class, 'membership_organization_id');
    }

    public function awards()
    {
        return $this->hasMany(Award::class, 'awarding_body_organization_id');
    }

    public function certifications()
    {
        return $this->hasMany(Certification::class, 'issuing_authority_organization_id');
    }

    public function researchProjects()
    {
        return $this->hasMany(ResearchProject::class, 'funding_agency_organization_id');
    }
}
