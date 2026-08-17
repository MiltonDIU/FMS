<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'api_url',
        'api_method',
        'mapping_config',
        'sample_json',
    ];

    protected $casts = [
        'mapping_config' => 'array',
        // Kept as the administrator typed it, so hand-edits round-trip exactly.
        // Encrypted because a real response carries the employee's date of
        // birth, address, phone numbers and last drawn salary.
        'sample_json' => 'encrypted',
    ];

    /**
     * List of supported models for mapping.
     */
    public static function getSupportedModels(): array
    {
        return [
            'User' => 'User',
            'Teacher' => 'Teacher Profile',
            'Education' => 'Educations (Relation)',
            'TrainingExperience' => 'Training Experiences (Relation)',
            'Publication' => 'Publications (Relation)',
            'Certification' => 'Certifications (Relation)',
            'Skill' => 'Skills (Relation)',
            'TeachingArea' => 'Teaching Areas (Relation)',
            'Membership' => 'Memberships (Relation)',
            'Award' => 'Awards (Relation)',
            'JobExperience' => 'Job Experiences (Relation)',
            'SocialLink' => 'Social Links (Relation)',
            'ResearchProject' => 'Research Projects (Relation)',
        ];
    }

    /**
     * Get fillable fields for a given model name.
     */
    public static function getModelFillableFields(string $modelName): array
    {
        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();

        return $model->getFillable();
    }

    /**
     * Flatten a nested array/JSON to dot notation paths.
     *
     * A list of objects — the educations, the publications, the awards — is
     * sampled from its first element so it yields
     * "employeeEducationalInformations.instituteName" style paths. That is
     * exactly the form a mapping row for a repeated section needs, and it is
     * what the seeded rules already use. Reporting the bare key instead left
     * every child collection unmappable from the screen, which matters because
     * the vendor documents twelve of them as nothing more than
     * "array of objects".
     *
     * Only the Integration Mapping screen's two field-detection buttons call
     * this; it does not touch how a saved mapping is applied.
     *
     * @return array<int,string>
     */
    public static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (! is_array($value)) {
                $result[] = $newKey;

                continue;
            }

            // An empty array names no fields. Reporting the bare key here made
            // an empty collection look like a scalar column of the record.
            if (empty($value)) {
                continue;
            }

            // Associative: descend into it.
            if (array_keys($value) !== range(0, count($value) - 1)) {
                $result = array_merge($result, self::flattenArray($value, $newKey));

                continue;
            }

            $first = $value[0] ?? null;

            if (is_array($first) && ! empty($first)) {
                // The rest of the list shares this element's shape.
                $result = array_merge($result, self::flattenArray($first, $newKey));

                continue;
            }

            // A list of plain values has no inner fields to name.
            $result[] = $newKey;
        }

        return $result;
    }
}
