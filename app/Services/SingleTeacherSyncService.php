<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\Publication;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SingleTeacherSyncService
{
    /**
     * Sync a specific teacher's profile data from the old database.
     *
     * @param Teacher $teacher
     * @param string $syncMode 'skip' or 'overwrite'
     * @return array
     */
    public function sync(Teacher $teacher, string $syncMode = 'skip'): array
    {
        $employeeId = $teacher->employee_id;
        if (empty($employeeId)) {
            return [
                'success' => false,
                'message' => 'Teacher has no employee ID assigned.',
            ];
        }

        // 1. If mode is overwrite, delete existing records first to ensure fresh import
        if ($syncMode === 'overwrite') {
            try {
                DB::transaction(function () use ($teacher) {
                    // Delete educations
                    $teacher->educations()->delete();

                    // Detach publications and delete orphaned ones (that have no other authors)
                    $pubIds = $teacher->publications()->pluck('publications.id')->toArray();
                    $teacher->publications()->detach();
                    if (!empty($pubIds)) {
                        Publication::whereIn('id', $pubIds)
                            ->whereDoesntHave('teachers')
                            ->delete();
                    }

                    // Delete awards
                    $teacher->awards()->delete();

                    // Delete teaching areas
                    $teacher->teachingAreas()->delete();

                    // Delete job experiences
                    $teacher->jobExperiences()->delete();

                    // Delete training experiences
                    $teacher->trainingExperiences()->delete();

                    // Delete memberships
                    $teacher->memberships()->delete();
                });
            } catch (\Exception $e) {
                Log::error("Failed to clear existing records for teacher {$employeeId}: " . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Failed to clear existing records: ' . $e->getMessage(),
                ];
            }
        }

        // 2. Define export/import steps for each category
        $tempFileSuffix = "_{$employeeId}_temp.json";
        $steps = [
            [
                'export' => 'export:old-teachers-educations',
                'import' => 'import:old-teachers-educations',
                'file'   => "teachers_educations_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:old-teachers-publications',
                'import' => 'import:old-teachers-publications',
                'file'   => "teachers_publications_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:old-teachers-memberships',
                'import' => 'import:old-teachers-memberships',
                'file'   => "teachers_memberships_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:old-teachers-awards',
                'import' => 'import:old-teachers-awards',
                'file'   => "teachers_awards_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:old-teachers-teaching-areas',
                'import' => 'import:old-teachers-teaching-areas',
                'file'   => "teachers_teaching_areas_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:old-teachers-job-experiences',
                'import' => 'import:old-teachers-job-experiences',
                'file'   => "teachers_job_experiences_parsed{$tempFileSuffix}",
            ],
            [
                'export' => 'export:training-experiences',
                'import' => 'import:training-experiences',
                'file'   => "training_experiences_export{$tempFileSuffix}",
            ],
        ];

        $errors = [];
        $syncedCount = 0;

        foreach ($steps as $step) {
            $exportFile = $step['file'];
            $exportFilePath = storage_path("app/public/exports/{$exportFile}");

            try {
                // Run the single-employee AI export command
                Artisan::call($step['export'], [
                    '--employee'   => $employeeId,
                    '--output'     => $exportFile,
                    '--overwrite'  => true,
                    '--provider'   => 'vertex', // Vertex Gemini is fast and configured
                ]);

                if (File::exists($exportFilePath)) {
                    $jsonContent = File::get($exportFilePath);
                    $records = json_decode($jsonContent, true);

                    if (!empty($records)) {
                        $importParams = [
                            '--file' => $exportFile,
                        ];

                        // If skip mode is enabled, supply skip-existing options to the importers
                        if ($syncMode === 'skip') {
                            $importParams['--skip-existing'] = true;
                        }

                        // Run import command
                        Artisan::call($step['import'], $importParams);
                        $syncedCount++;
                    }

                    // Delete the temporary file
                    File::delete($exportFilePath);
                }
            } catch (\Exception $e) {
                Log::error("Sync failed for command {$step['export']} / {$step['import']}: " . $e->getMessage());
                $errors[] = $step['export'] . ': ' . $e->getMessage();
                // Ensure temp file is cleaned up on error
                if (File::exists($exportFilePath)) {
                    File::delete($exportFilePath);
                }
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Sync completed with some errors: ' . implode(', ', $errors),
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully synced {$syncedCount} categories of profile data from the old database.",
        ];
    }
}
