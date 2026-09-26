<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Department;
use App\Models\Major;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Teacher;
use App\Models\User;

/**
 * Writes one of a teacher's repeated sections — awards, educations,
 * publications and the rest — from the rows the teacher form produces.
 *
 * There used to be two writers. The form's own saveRelationshipsUsing
 * closures knew each section: they filled the institution, organisation and
 * position names from the chosen ids, numbered the rows in the order shown,
 * flattened an award's upload into its path, and kept "featured" to the
 * people allowed to set it. TeacherVersionService had a generic one that
 * copied whatever fillable keys a row happened to carry. Every edit on the
 * profile pages goes through the service, so an approved change could leave
 * the old institution name on screen, lose the order, or let a teacher
 * feature their own paper; and it looked rows up by id alone, so a row id
 * edited in the request reached another teacher's records.
 *
 * Now both call this, and rows are only ever found through the teacher.
 */
final class TeacherRelationWriter
{
    /** The sections written here, by relation name. */
    public const RELATIONS = [
        'educations', 'publications', 'jobExperiences', 'trainingExperiences', 'awards',
        'skills', 'teachingAreas', 'researchInterests', 'memberships', 'socialLinks',
    ];

    /**
     * @param  array<int|string, mixed>  $rows  the section as the form shows it, in order
     * @param  array<int, int>|null  $removedIds  rows to remove; null means "every
     *                                          existing row the list leaves out", which
     *                                          is only right when the list is complete
     * @param  User|null  $actor  who made the change — the submitter when a version is
     *                           approved, not the approver; defaults to whoever is signed in
     */
    public static function save(Teacher $teacher, string $relation, array $rows, ?array $removedIds = null, ?User $actor = null): void
    {
        if (! in_array($relation, self::RELATIONS, true)) {
            throw new \InvalidArgumentException("Unknown teacher relation [{$relation}].");
        }

        $rows = array_values(array_filter($rows, 'is_array'));
        $actor ??= auth()->user();

        self::remove($teacher, $relation, $rows, $removedIds);

        foreach ($rows as $position => $item) {
            if ($relation === 'publications') {
                self::savePublication($teacher, $item, $position, $actor);

                continue;
            }

            $data = self::attributes($relation, $item, $position);

            if (! empty($item['id'])) {
                // Through the teacher, so an id that is not theirs finds nothing.
                $teacher->$relation()->whereKey($item['id'])->first()?->update($data);
            } else {
                $teacher->$relation()->create($data);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>|null  $removedIds
     */
    private static function remove(Teacher $teacher, string $relation, array $rows, ?array $removedIds): void
    {
        $kept = collect($rows)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        if ($relation === 'publications') {
            $existing = $teacher->publications()->pluck('publications.id')->map(fn ($id): int => (int) $id)->all();
            $remove = array_intersect($removedIds ?? array_diff($existing, $kept), $existing);

            // Detached, not deleted: a paper is shared by every author on it,
            // and leaving one profile must not take it off the others.
            if ($remove !== []) {
                $teacher->publications()->detach($remove);
            }

            return;
        }

        $query = $teacher->$relation();

        $removedIds === null
            ? $query->whereNotIn('id', $kept)->delete()
            : $query->whereIn('id', $removedIds)->delete();
    }

    /**
     * The columns one row writes, per section.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function attributes(string $relation, array $item, int $position): array
    {
        return match ($relation) {
            'researchInterests' => [
                'interest' => $item['interest'],
                'description' => $item['description'] ?? null,
                'sort_order' => $position,
            ],
            'teachingAreas' => [
                'area' => $item['area'],
                'description' => $item['description'] ?? null,
                'sort_order' => $position,
            ],
            'skills' => [
                'name' => $item['name'],
                'proficiency' => $item['proficiency'] ?? null,
                'sort_order' => $position,
            ],
            'educations' => [
                'degree_type_id' => $item['degree_type_id'],
                'educational_institution_id' => $item['educational_institution_id'] ?? null,
                'major_id' => $item['major_id'] ?? null,
                'major' => self::nameOf(Major::class, $item['major_id'] ?? null),
                'institution' => self::nameOf(Organization::class, $item['educational_institution_id'] ?? null),
                'country_id' => $item['country_id'] ?? null,
                'passing_year' => $item['passing_year'] ?? null,
                'duration' => $item['duration'] ?? null,
                'result_type_id' => $item['result_type_id'],
                'cgpa' => $item['cgpa'] ?? null,
                'scale' => $item['scale'] ?? null,
                'marks' => $item['marks'] ?? null,
                'grade' => $item['grade'] ?? null,
                'sort_order' => $position,
            ],
            'jobExperiences' => [
                'position_id' => $item['position_id'] ?? null,
                'position' => self::nameOf(Position::class, $item['position_id'] ?? null) ?? '',
                'organization_id' => $item['organization_id'] ?? null,
                'organization' => self::nameOf(Organization::class, $item['organization_id'] ?? null) ?? '',
                'country_id' => $item['country_id'] ?? null,
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'] ?? null,
                'is_current' => $item['is_current'] ?? false,
                'department' => $item['department'] ?? null,
                'responsibilities' => $item['responsibilities'] ?? null,
                'sort_order' => $position,
            ],
            'trainingExperiences' => [
                'title' => $item['title'],
                'organization_id' => $item['organization_id'] ?? null,
                'organization' => self::nameOf(Organization::class, $item['organization_id'] ?? null) ?? '',
                'category' => $item['category'] ?? null,
                'country_id' => $item['country_id'] ?? null,
                'year' => $item['year'] ?? null,
                'completion_date' => $item['completion_date'] ?? null,
                'duration_days' => $item['duration_days'] ?? null,
                'is_online' => $item['is_online'] ?? false,
                'description' => $item['description'] ?? null,
                'sort_order' => $position,
            ],
            'awards' => self::awardAttributes($item, $position),
            'memberships' => [
                'membership_organization_id' => $item['membership_organization_id'],
                'membership_type_id' => $item['membership_type_id'] ?? null,
                'record_type' => $item['record_type'] ?? 'membership',
                'position' => $item['position'] ?? null,
                'scope' => $item['scope'] ?? null,
                'url' => $item['url'] ?? null,
                'membership_id' => $item['membership_id'] ?? null,
                'start_date' => $item['start_date'] ?? null,
                'end_date' => $item['end_date'] ?? null,
                'status' => $item['status'] ?? 'active',
                'description' => $item['description'] ?? null,
                'sort_order' => $position,
            ],
            'socialLinks' => [
                'social_media_platform_id' => $item['social_media_platform_id'],
                'username' => $item['username'],
                'url' => $item['url'],
                'sort_order' => $position + 1,
            ],
        };
    }

    /**
     * An upload arrives as the widget's keyed array rather than a path, and
     * rows imported from the old site carry arrays in fields that should be
     * text; both are flattened the way the form always did.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function awardAttributes(array $item, int $position): array
    {
        $first = fn ($value, $fallback = null) => is_array($value) ? (reset($value) ?: $fallback) : ($value ?? $fallback);

        return [
            'title' => $first($item['title'] ?? null, 'Award'),
            'awarding_body' => $first($item['awarding_body'] ?? null),
            'type' => is_array($item['type'] ?? null) ? 'award' : ($item['type'] ?? 'award'),
            'date' => is_array($item['date'] ?? null) ? null : ($item['date'] ?? null),
            'year' => is_array($item['year'] ?? null) ? null : ($item['year'] ?? null),
            'remarks' => is_array($item['remarks'] ?? null) ? json_encode($item['remarks']) : ($item['remarks'] ?? null),
            'attachment' => $first($item['attachment'] ?? null),
            'sort_order' => $position,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function savePublication(Teacher $teacher, array $item, int $position, ?User $actor): void
    {
        $deptId = $item['department_id'] ?? $teacher->department_id;
        $facultyId = $item['faculty_id']
            ?? ($deptId ? Department::find($deptId)?->faculty_id : $teacher->department?->faculty_id);

        $existing = ! empty($item['id'])
            ? $teacher->publications()->where('publications.id', $item['id'])->first()
            : null;

        if (! empty($item['id']) && ! $existing) {
            // Not one of this teacher's papers.
            return;
        }

        $canManageFeatured = $actor && (
            $actor->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team'])
            || $actor->administrativeRoles()->where('administrative_role_user.is_active', true)->exists()
        );

        $data = [
            'faculty_id' => $facultyId,
            'department_id' => $deptId,
            'publication_type_id' => $item['publication_type_id'],
            'publication_linkage_id' => $item['publication_linkage_id'],
            'publication_quartile_id' => $item['publication_quartile_id'] ?? null,
            'grant_type_id' => $item['grant_type_id'] ?? null,
            'research_collaboration_id' => $item['research_collaboration_id'] ?? null,
            'title' => $item['title'],
            'abstract' => $item['abstract'] ?? null,
            'research_area' => $item['research_area'] ?? null,
            'keywords' => $item['keywords'] ?? null,
            'journal_name' => $item['journal_name'] ?? null,
            'journal_link' => $item['journal_link'] ?? null,
            'publication_date' => $item['publication_date'] ?? null,
            'publication_year' => $item['publication_year'] ?? null,
            'h_index' => $item['h_index'] ?? null,
            'citescore' => $item['citescore'] ?? null,
            'impact_factor' => $item['impact_factor'] ?? null,
            'student_involvement' => $item['student_involvement'] ?? false,
            'is_featured' => $canManageFeatured
                ? (bool) ($item['is_featured'] ?? false)
                : (bool) ($existing?->is_featured ?? false),
            'sort_order' => $position,
        ];

        if ($existing) {
            $existing->update($data);
            $publication = $existing;
        } else {
            // The form asked ApprovalSetting::requiresApproval('publication'),
            // a key that has never existed, so this was always "approved". It
            // is still right: a new paper reaches here either straight from an
            // admin or after its section was approved.
            $publication = $teacher->publications()->create($data + ['status' => 'approved']);
        }

        PublicationAuthorship::write($publication, $item, $teacher);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private static function nameOf(string $model, mixed $id): ?string
    {
        return filled($id) ? $model::find($id)?->name : null;
    }
}
