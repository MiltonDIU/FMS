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
                'London', 'Manchester', 'Cambridge', 'Oxford', 'Coventry', 'IET',
                'Lancaster', 'Leeds', 'Nottingham', 'Sheffield', 'Southampton', 
                'Birmingham', 'Cardiff', 'Glasgow', 'Edinburgh', 'Surrey', 'Sussex', 
                'Warwick', 'York'
            ],
            // United States (250)
            250 => [
                'USA', 'United States', 'U.S.A.', 'America', 'US ', 'U.S. ', 'Washington',
                'California', 'Harvard', 'MIT', 'Stanford', 'IEEE', 'USAID', 'ACM',
                'Texas', 'Boston', 'Chicago', 'Michigan', 'Illinois', 'Florida', 'NY', 
                'New York', 'Yale', 'Princeton', 'Columbia', 'Cornell', 'Penn'
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
     * Normalize country names and standard abbreviations (e.g. "UK", "USA", "UAE").
     */
    public static function normalizeCountryName(string $name): string
    {
        $name = trim($name);
        $lower = mb_strtolower($name);
        
        $map = [
            'uk'                       => 'United Kingdom',
            'u.k'                      => 'United Kingdom',
            'u.k.'                     => 'United Kingdom',
            'england'                  => 'United Kingdom',
            'great britain'            => 'United Kingdom',
            'scotland'                 => 'United Kingdom',
            'wales'                    => 'United Kingdom',
            'usa'                      => 'United States',
            'u.s.a'                    => 'United States',
            'u.s.a.'                   => 'United States',
            'us'                       => 'United States',
            'u.s'                      => 'United States',
            'u.s.'                     => 'United States',
            'united states of america' => 'United States',
            'uae'                      => 'United Arab Emirates',
            'u.a.e'                     => 'United Arab Emirates',
            'u.a.e.'                    => 'United Arab Emirates',
            'united arab rows'          => 'United Arab Emirates',
            'ksa'                      => 'Saudi Arabia',
            'k.s.a'                     => 'Saudi Arabia',
            'k.s.a.'                    => 'Saudi Arabia',
            'saudi arab'                => 'Saudi Arabia',
            'bangladesh'               => 'Bangladesh',
            'bd'                       => 'Bangladesh',
            'b.d'                      => 'Bangladesh',
            'b.d.'                     => 'Bangladesh',
        ];
        
        return $map[$lower] ?? $name;
    }

    /**
     * Get a canonicalized version of the organization name for comparison.
     */
    public static function getCanonicalName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $lower = mb_strtolower($name);
        
        // Strip short acronyms in parentheses first (e.g. "(NIIT)", "(DIU)")
        $lower = preg_replace('/\s*\([a-z]{2,6}\)/', '', $lower);
        
        // Strip common punctuation and remaining brackets
        $lower = str_replace(['.', ',', '(', ')', '[', ']'], '', $lower);
        
        // Remove leading "the"
        $lower = preg_replace('/^the\s+/', '', $lower);
        
        // Map common abbreviations to full names
        $abbreviations = [
            'diu'   => 'daffodil international university',
            'du'    => 'university of dhaka',
            'ju'    => 'jahangirnagar university',
            'ru'    => 'rajshahi university',
            'cu'   => 'chittagong university',
            'buet'  => 'bangladesh university of engineering and technology',
            'niit'  => 'national institute of information technology',
            'niffa' => 'national institute of film and fine arts',
        ];
        if (isset($abbreviations[$lower])) {
            return $abbreviations[$lower];
        }
        
        // Transform "university of X" to "X university" for standard comparison
        if (preg_match('/^university\s+of\s+(.+)$/', $lower, $matches)) {
            return trim($matches[1]) . ' university';
        }
        
        return $lower;
    }

    /**
     * Get the canonical child name by removing the parent part.
     */
    public static function getCanonicalChildName(string $name): string
    {
        $name = trim($name);
        $parentName = self::extractParentName($name);
        if (!$parentName) {
            return self::getCanonicalName($name);
        }
        
        // Remove parent name from child name (case-insensitive)
        $child = preg_replace('/' . preg_quote($parentName, '/') . '/i', '', $name);
        
        // Remove other common separators
        $child = preg_replace('/\bof\b/i', '', $child);
        $child = preg_replace('/\bat\b/i', '', $child);
        
        return self::getCanonicalName($child);
    }

    /**
     * Try to extract and resolve parent organization from a name.
     */
    public static function extractParentName(string $name): ?string
    {
        $name = trim($name);
        
        // Pattern 1: "Child Name, Parent Name"
        if (str_contains($name, ',')) {
            $parts = array_map('trim', explode(',', $name));
            if (count($parts) > 1) {
                return end($parts);
            }
        }
        
        // Pattern 2: "Child Name of Parent Name"
        if (preg_match('/^(.+)\s+of\s+(.+)$/i', $name, $matches)) {
            $childPart = trim($matches[1]);
            $parentCandidate = trim($matches[2]);
            
            // Only split by "of" if the child part contains a known sub-unit indicator
            $subUnitKeywords = ['department', 'dept', 'division', 'bureau', 'club', 'alumni', 'association', 'office', 'faculty', 'center'];
            $isSubUnit = false;
            foreach ($subUnitKeywords as $kw) {
                if (stripos($childPart, $kw) !== false) {
                    $isSubUnit = true;
                    break;
                }
            }
            
            if ($isSubUnit && !in_array(strtolower($parentCandidate), ['bangladesh', 'science', 'arts', 'engineering'])) {
                return $parentCandidate;
            }
        }
        
        // Pattern 3: "Child Name at Parent Name"
        if (preg_match('/^(.+)\s+at\s+(.+)$/i', $name, $matches)) {
            return trim($matches[2]);
        }
        
        // Pattern 4: "Child Name (Parent Name)"
        if (preg_match('/(.+)\s*\(([^)]+)\)$/', $name, $matches)) {
            $parentCandidate = trim($matches[2]);
            // If it is a short acronym, it's an abbreviation of the current org, not a parent!
            if (strlen($parentCandidate) > 1 && !preg_match('/^[A-Za-z]{2,6}$/', $parentCandidate)) {
                return $parentCandidate;
            }
        }

        return null;
    }

    /**
     * Case-insensitive find or create with auto-approval logic, smart similarity matching,
     * and dynamic parent-child resolution.
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

        // Resolve parent organization if it already exists in the database
        $parentId = null;
        $parentName = self::extractParentName($name);
        if ($parentName && self::getCanonicalName($name) !== self::getCanonicalName($parentName)) {
            $canonicalParent = self::getCanonicalName($parentName);
            $parentOrg = self::where(function ($query) use ($countryId) {
                    if ($countryId) {
                        $query->where('country_id', $countryId)
                              ->orWhereNull('country_id');
                    }
                })
                ->get()
                ->first(function ($org) use ($canonicalParent) {
                    return self::getCanonicalName($org->name) === $canonicalParent;
                });
            
            if ($parentOrg) {
                $parentId = $parentOrg->id;
            }
        }

        // Match similar organization names dynamically using canonical names
        $canonicalInput = self::getCanonicalName($name);
        $childNameInput = self::getCanonicalChildName($name);
        
        $existing = self::where(function ($query) use ($countryId) {
                if ($countryId) {
                    $query->where('country_id', $countryId)
                          ->orWhereNull('country_id');
                }
            })
            ->get()
            ->sortBy(function ($org) use ($countryId) {
                if ($countryId) {
                    return $org->country_id == $countryId ? 0 : 1;
                }
                return $org->country_id === null ? 0 : 1;
            })
            ->first(function ($org) use ($canonicalInput, $parentId, $childNameInput) {
                // Exact canonical match
                if (self::getCanonicalName($org->name) === $canonicalInput) {
                    return true;
                }
                // Parent-Child match (same parent and same child name)
                if ($parentId && $org->parent_id == $parentId) {
                    if (self::getCanonicalChildName($org->name) === $childNameInput) {
                        return true;
                    }
                }
                return false;
            });

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
            if ($parentId && !$existing->parent_id) {
                $update['parent_id'] = $parentId;
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
            'parent_id'   => $parentId,
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
