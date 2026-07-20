<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\On;

class CreateTeacher extends CreateRecord
{
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
        /** @var \App\Services\IntegrationService $integrationService */
        $integrationService = app(\App\Services\IntegrationService::class);

        $mappingSlug = \App\Models\Setting::get('teacher_integration_mapping', 'erp_teacher_profile');

        // 1. Fetch full preview payload if search item only passed brief details
        $rawPayload = $teacher;
        $searchKey = $teacher['employee_id'] ?? $teacher['employeeID'] ?? $teacher['webpage'] ?? $teacher['email'] ?? $teacher['name'] ?? null;

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
