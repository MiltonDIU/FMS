<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\HasWindowedRepeaters;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\On;

class CreateTeacher extends CreateRecord
{
    use HasWindowedRepeaters;

    protected static string $resource = TeacherResource::class;

    /**
     * Store the email in session before creating the teacher
     * so the Observer can access it.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store email in session for Observer to use
        if (isset($data['email'])) {
            session(['teacher_creation_email' => $data['email']]);
        }

        // Remove email from data as it's not a Teacher column
        unset($data['email']);

        if (empty($data['sort_order'])) {
            $data['sort_order'] = (\App\Models\Teacher::max('sort_order') ?? \App\Models\Teacher::count()) + 1;
        }

        return $data;
    }

    /**
     * Clean up session after creation.
     */
    protected function afterCreate(): void
    {
        session()->forget('teacher_creation_email');
    }

    /**
     * Handle auto-fill & full relational import from teacher search
     */
    #[On('fillTeacherData')]
    public function fillTeacherData(array $teacher): void
    {
        /*
         * The search marks rows we already hold and offers "Merge Profile", but
         * this is the create screen: pressing Create then tried to insert a
         * second teacher for the same person, and teachers.user_id is unique, so
         * it failed on a constraint. Send them to that teacher's edit screen
         * instead, where the merge actually happens and can be reviewed.
         */
        $employeeId = $teacher['employee_id'] ?? $teacher['employeeID'] ?? null;

        if ($employeeId) {
            $existing = \App\Models\Teacher::where('employee_id', $employeeId)->first();

            if ($existing) {
                \Filament\Notifications\Notification::make()
                    ->title('This teacher is already on file')
                    ->body('Opening their profile so the incoming data can be merged into it. Review the changes, then save.')
                    ->info()
                    ->send();

                $this->redirect(TeacherResource::getUrl('edit', [
                    'record' => $existing,
                    'hrMerge' => $employeeId,
                ]));

                return;
            }
        }

        /** @var \App\Services\IntegrationService $integrationService */
        $integrationService = app(\App\Services\IntegrationService::class);

        $mappingSlug = \App\Models\Setting::get('teacher_integration_mapping', 'erp_teacher_profile');

        // 1. Fetch full preview payload if search item only passed brief details
        $rawPayload = $teacher;
        $searchKey = $teacher['employee_id'] ?? $teacher['employeeID'] ?? $teacher['webpage'] ?? $teacher['email'] ?? $teacher['name'] ?? null;

        // A row that came from the HR API is completed from the HR API: that is
        // where the educations, publications and job experiences live. Rows from
        // the legacy search keep going through the preview path below.
        if (($teacher['source'] ?? null) === 'hr_api' && $searchKey) {
            try {
                $profile = app(\App\Services\HrApiService::class)->getTeacherProfile((string) $searchKey);

                if ($profile === null) {
                    \Filament\Notifications\Notification::make()
                        ->title('No profile found')
                        ->body("The HR API has no profile for employee {$searchKey}.")
                        ->warning()
                        ->send();

                    return;
                }

                $rawPayload = $profile;
                $searchKey = null; // Already have the full record; skip the preview lookup.
            } catch (\RuntimeException $e) {
                \Filament\Notifications\Notification::make()
                    ->title('Could not load that profile')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        if ($searchKey) {
            $controller = app(\App\Http\Controllers\Api\V1\FrontendApiController::class);
            $req = \Illuminate\Http\Request::create('/api/v1/teachers/preview', 'GET', [
                'q' => $searchKey,
                'employee_id' => $searchKey,
                'webpage' => $searchKey,
            ]);
            $res = $controller->previewTeacherImport($req);
            $resData = json_decode($res->getContent(), true);

            if (!empty($resData['raw_payload'])) {
                $rawPayload = $resData['raw_payload'];
            }
        }

        // 2. Transform raw payload to mapped structure
        $overview = $integrationService->transform((array) $rawPayload, $mappingSlug);

        // 3. Assemble & resolve complete form data with all lookup relationships for tab repeaters
        $formData = \App\Helpers\FormPayloadResolver::resolveForForm($overview);

        // 4. Fill Filament form
        $this->form->fill($formData);

        $eduCount = count($formData['educations']);
        $skillCount = count($formData['skills']);
        $jobCount = count($formData['jobExperiences']);
        $pubCount = count($formData['publications']);

        \Filament\Notifications\Notification::make()
            ->title('All Profile Fields Auto-Filled!')
            ->body("Form inputs and all tab repeaters populated from API ({$eduCount} Educations, {$skillCount} Skills, {$jobCount} Job Experiences, {$pubCount} Publications). Please review tabs and click Create.")
            ->success()
            ->send();
    }

    /**
     * Display legacy teacher search above the form
     */
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.resources.teachers.components.legacy-teacher-search');
    }
}
