<?php

namespace Database\Seeders;

use App\Helpers\FormPayloadResolver;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\HrApiService;
use App\Services\IntegrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pulls teachers straight from the HR API into the main tables.
 *
 * Does exactly what the Create Teacher screen does when somebody searches and
 * presses import — same mapping, same lookups, same name splitting, same
 * approval defaults — only without doing it one person at a time.
 *
 * Which employees:
 *     HR_IMPORT_EMPLOYEE_IDS=<id>,<id>   a specific list, or
 *     HR_IMPORT_LIMIT=25                 the first N from the directory
 *
 * Run with:
 *     php artisan db:seed --class=HrTeacherImportSeeder
 *
 * Safe to run repeatedly: a teacher is matched on employee_id and updated in
 * place, and each one commits separately so a single bad record cannot undo the
 * rest.
 */
class HrTeacherImportSeeder extends Seeder
{
    /**
     * Relation arrays produced by FormPayloadResolver, and the relation they
     * belong to. Publications are absent on purpose: they are shared records
     * behind a pivot, with their own de-duplicating import.
     */
    protected const RELATIONS = [
        'educations' => 'educations',
        'trainingExperiences' => 'trainingExperiences',
        'certifications' => 'certifications',
        'skills' => 'skills',
        'teachingAreas' => 'teachingAreas',
        'memberships' => 'memberships',
        'awards' => 'awards',
        'jobExperiences' => 'jobExperiences',
        'socialLinks' => 'socialLinks',
    ];

    public function run(): void
    {
        /** @var HrApiService $hrApi */
        $hrApi = app(HrApiService::class);

        if (! $hrApi->isConfigured()) {
            $this->command?->error('The HR API is not configured. Fill it in under System Settings → Teacher API Integration first.');

            return;
        }

        $employeeIds = $this->employeeIds($hrApi);

        if ($employeeIds === []) {
            $this->command?->warn('No employee IDs to import.');

            return;
        }

        $this->command?->info('Importing ' . count($employeeIds) . ' teacher(s) from the HR API...');

        $created = $updated = $skipped = 0;
        $failures = [];

        foreach ($employeeIds as $employeeId) {
            try {
                $outcome = $this->importOne($hrApi, (string) $employeeId);

                match ($outcome) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $skipped++,
                };
            } catch (\Throwable $e) {
                $skipped++;
                $failures[] = "{$employeeId}: " . $e->getMessage();
            }
        }

        $this->command?->info("Created {$created}, updated {$updated}, skipped {$skipped}.");

        foreach ($failures as $failure) {
            $this->command?->warn('  ' . $failure);
        }
    }

    /**
     * Whose profiles to fetch.
     *
     * @return array<int,string>
     */
    protected function employeeIds(HrApiService $hrApi): array
    {
        $configured = trim((string) env('HR_IMPORT_EMPLOYEE_IDS', ''));

        if ($configured !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        $limit = (int) env('HR_IMPORT_LIMIT', 10);

        // A blank search term lists the directory from the top.
        $records = $hrApi->searchTeachers('', 1, max(1, $limit));

        return array_values(array_filter(array_map(
            fn (array $record) => $record['employee_id'] ?? null,
            $records,
        )));
    }

    /**
     * Fetch one profile and write it, returning what happened.
     */
    protected function importOne(HrApiService $hrApi, string $employeeId): string
    {
        $profile = $hrApi->getTeacherProfile($employeeId);

        if ($profile === null) {
            throw new \RuntimeException('no profile returned');
        }

        $slug = (string) Setting::get('teacher_integration_mapping', 'erp_teacher_profile');

        $overview = app(IntegrationService::class)->transform($profile, $slug);

        // Everything the form would have shown, with foreign keys resolved.
        $formData = FormPayloadResolver::resolveForForm($overview);

        $email = $formData['email']
            ?? $overview['User']['email']
            ?? $formData['secondary_email']
            ?? null;

        if (blank($formData['first_name'] ?? null)) {
            throw new \RuntimeException('no name in the payload');
        }

        /*
         * department_id and designation_id are NOT NULL on teachers, so a
         * profile whose department could not be resolved has to be reported
         * rather than left to fail as a SQL error nobody can read. It happens
         * for real: some employees carry their faculty in the department field,
         * and the mapping in use may have no rule for the column at all.
         */
        foreach (['department_id' => 'department', 'designation_id' => 'designation'] as $column => $label) {
            if (blank($formData[$column] ?? null)) {
                throw new \RuntimeException(
                    "{$label} did not resolve (sent: \"" . ($profile[$label] ?? '—') . '"). '
                    . "Check the mapping has a rule for {$column}, and that the value exists locally."
                );
            }
        }

        return DB::transaction(function () use ($employeeId, $formData, $email) {
            $existing = Teacher::where('employee_id', $employeeId)->first();

            $user = $this->resolveUser($existing, $formData, $email, $employeeId);

            $attributes = $this->teacherAttributes($formData);
            $attributes['user_id'] = $user->id;
            $attributes['employee_id'] = $employeeId;

            if ($existing) {
                $existing->update($attributes);
                $teacher = $existing;
            } else {
                $teacher = Teacher::create($attributes);
            }

            $this->syncRelations($teacher, $formData);

            return $existing ? 'updated' : 'created';
        });
    }

    /**
     * The account behind the teacher.
     *
     * Created here rather than left to the observer, because the observer's
     * auto-creation queues a welcome email with a default password — fine for
     * one person added by hand, wrong for a bulk run.
     *
     * @param array<string,mixed> $formData
     */
    protected function resolveUser(?Teacher $existing, array $formData, ?string $email, string $employeeId): User
    {
        if ($existing?->user_id && $user = User::find($existing->user_id)) {
            return $user;
        }

        $email = $email ?: $employeeId . '@daffodilvarsity.edu.bd';

        $name = trim(implode(' ', array_filter([
            $formData['first_name'] ?? null,
            $formData['middle_name'] ?? null,
            $formData['last_name'] ?? null,
        ])));

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'Teacher',
                // A random password, not a shared default: these accounts are
                // reached through the password-setup link, never a known one.
                'password' => bcrypt(Str::random(32)),
                'is_active' => true,
            ],
        );
    }

    /**
     * Reduce the form payload to the teacher's own columns.
     *
     * @param array<string,mixed> $formData
     * @return array<string,mixed>
     */
    protected function teacherAttributes(array $formData): array
    {
        $fillable = (new Teacher())->getFillable();

        return array_filter(
            array_intersect_key($formData, array_flip($fillable)),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * Replace each relation with what the API just reported.
     *
     * Replace rather than append: re-running must not leave a teacher with the
     * same degree recorded four times.
     *
     * @param array<string,mixed> $formData
     */
    protected function syncRelations(Teacher $teacher, array $formData): void
    {
        foreach (self::RELATIONS as $key => $relation) {
            $rows = $formData[$key] ?? [];

            if (! is_array($rows) || ! method_exists($teacher, $relation)) {
                continue;
            }

            $related = $teacher->$relation()->getRelated();
            $fillable = array_flip($related->getFillable());

            $clean = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $row = array_intersect_key($row, $fillable);
                $row = array_filter($row, fn ($value) => $value !== null && $value !== '');

                if ($row !== []) {
                    $clean[] = $row;
                }
            }

            // Nothing came back for this section: leave whatever is on file
            // rather than wiping data the API simply did not mention.
            if ($clean === []) {
                continue;
            }

            $teacher->$relation()->delete();

            foreach ($clean as $row) {
                $teacher->$relation()->create($row);
            }
        }
    }
}
