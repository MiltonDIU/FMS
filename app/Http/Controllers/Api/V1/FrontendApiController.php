<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Teacher;
use App\Services\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ERP teacher import preview.
 *
 * The public read endpoints that once lived here (faculties, departments,
 * designations, teacher listings, profile lookups) existed to feed a planned
 * Next.js frontend. That frontend was built as a Blade + Livewire monolith
 * instead, so those methods and their routes have been removed.
 *
 * What remains has no route by design: CreateTeacher and SystemSettings call
 * previewTeacherImport() in-process, never over HTTP.
 *
 * @see \App\Filament\Resources\Teachers\Pages\CreateTeacher::fillTeacherData()
 * @see \App\Filament\Pages\SystemSettings::searchTeacherApi()
 */
class FrontendApiController extends Controller
{
    /**
     * Build a preview of an incoming teacher payload without writing anything.
     *
     * Resolves the payload from an explicit `payload`, or by looking up
     * `employee_id` / `webpage` / `q` on the API-only connection, then maps it
     * through the configured integration mapping and reports whether a
     * matching teacher already exists locally.
     */
    public function previewTeacherImport(Request $request): JsonResponse
    {
        $rawPayload = $request->input('payload');

        // If no raw payload passed directly, search by employee_id / webpage / q
        if (empty($rawPayload)) {
            $employeeId = $request->input('employee_id');
            $webpage = $request->input('webpage');
            $q = $request->input('q') ?? $employeeId ?? $webpage;

            $connName = config('database.connections.api_only_db') ? 'api_only_db' : config('database.default');

            $teacherObj = null;

            if ($webpage) {
                $teacherObj = Teacher::on($connName)->where('webpage', $webpage)->first();
            }
            if (!$teacherObj && $employeeId) {
                $teacherObj = Teacher::on($connName)->where('employee_id', $employeeId)->first();
            }
            if (!$teacherObj && $q) {
                $teacherObj = Teacher::on($connName)
                    ->where('employee_id', $q)
                    ->orWhere('webpage', $q)
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->first();
            }

            if ($teacherObj) {
                $teacherObj->load([
                    'user',
                    'educations',
                    'skills',
                    'trainingExperiences',
                    'certifications',
                    'publications',
                    'jobExperiences',
                    'awards',
                    'memberships',
                    'socialLinks',
                    'teachingAreas',
                ]);

                $rawPayload = [
                    'data' => array_merge($teacherObj->toArray(), [
                        'email' => $teacherObj->user?->email ?? $teacherObj->secondary_email,
                        'employee_id' => $teacherObj->employee_id,
                        'webpage' => $teacherObj->webpage,
                        'first_name' => $teacherObj->first_name,
                        'last_name' => $teacherObj->last_name,
                        'phone' => $teacherObj->phone,
                        'educations' => $teacherObj->educations->map(fn($e) => [
                            'institution' => $e->institution,
                            'major' => $e->major,
                            'passing_year' => $e->passing_year,
                            'duration' => $e->duration,
                            'cgpa' => $e->cgpa,
                            'scale' => $e->scale,
                            'grade' => $e->grade,
                        ])->toArray(),
                        'skills' => $teacherObj->skills->map(fn($s) => [
                            'name' => $s->name,
                            'proficiency' => $s->proficiency,
                        ])->toArray(),
                        'job_experiences' => $teacherObj->jobExperiences->map(fn($j) => [
                            'position' => $j->position,
                            'organization' => $j->organization,
                            'department' => $j->department,
                            'start_date' => $j->start_date ? $j->start_date->toDateString() : null,
                            'end_date' => $j->end_date ? $j->end_date->toDateString() : null,
                        ])->toArray(),
                        'training_experiences' => $teacherObj->trainingExperiences->map(fn($t) => [
                            'title' => $t->title,
                            'organization' => $t->organization,
                            'category' => $t->category,
                            'duration_days' => $t->duration_days,
                            'completion_date' => $t->completion_date ? $t->completion_date->toDateString() : null,
                        ])->toArray(),
                        'certifications' => $teacherObj->certifications->map(fn($c) => [
                            'title' => $c->title,
                            'issuing_authority' => $c->issuing_authority,
                            'credential_id' => $c->credential_id,
                        ])->toArray(),
                        'publications' => $teacherObj->publications->map(fn($p) => [
                            'title' => $p->title,
                            'journal_name' => $p->journal_name,
                            'publication_year' => $p->publication_year,
                        ])->toArray(),
                        'social_links' => $teacherObj->socialLinks->map(fn($s) => [
                            'username' => $s->username,
                            'url' => $s->url,
                        ])->toArray(),
                    ])
                ];
            }

            // Fallback: check profile.json or legacy search
            if (empty($rawPayload)) {
                $sampleFile = public_path('documents/profile.json');
                if (file_exists($sampleFile)) {
                    $rawPayload = json_decode(file_get_contents($sampleFile), true);
                }
            }
        }

        if (empty($rawPayload)) {
            return response()->json([
                'success' => false,
                'message' => 'No teacher data found to preview.',
            ], 404);
        }

        $integrationService = app(IntegrationService::class);
        $mappingSlug = Setting::get('teacher_integration_mapping', 'erp_teacher_profile');
        $overview = $integrationService->transform((array) $rawPayload, $mappingSlug);

        $empId = $overview['Teacher']['employee_id'] ?? null;
        $wp = $overview['Teacher']['webpage'] ?? null;
        $email = $overview['User']['email'] ?? $overview['Teacher']['secondary_email'] ?? null;

        $existingTeacher = Teacher::query()
            ->when($empId, fn($q) => $q->where('employee_id', $empId))
            ->when($wp, fn($q) => $q->orWhere('webpage', $wp))
            ->when($email, fn($q) => $q->orWhere('secondary_email', $email))
            ->first();

        return response()->json([
            'success' => true,
            'preview' => true,
            'exists_locally' => (bool) $existingTeacher,
            'action' => $existingTeacher ? 'update' : 'create',
            'teacher_id' => $existingTeacher ? $existingTeacher->id : null,
            'raw_payload' => $rawPayload,
            'overview' => $overview,
        ]);
    }
}
