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
     * Handle auto-fill from legacy teacher search
     */
    #[On('fillTeacherData')]
    public function fillTeacherData(array $teacher): void
    {
        \Log::info('Received teacher data for auto-fill:', $teacher);

        /** @var \App\Services\IntegrationService $integrationService */
        $integrationService = app(\App\Services\IntegrationService::class);

        // Apply integration mapping transformation on raw legacy data
        $mappedData = $integrationService->transform($teacher, 'legacy_teacher_search');

        \Log::info('Mapped data from IntegrationService:', $mappedData);

        // Flatten the nested structure (e.g., ['Teacher' => ['field' => 'value']] to ['field' => 'value'])
        $formData = [];
        foreach ($mappedData as $key => $value) {
            if (is_array($value)) {
                // If it's a nested array (model-based), merge it
                $formData = array_merge($formData, $value);
            } else {
                // If it's a flat field, add it directly
                $formData[$key] = $value;
            }
        }

        \Log::info('Final form data to fill:', $formData);

        // Fill the form with flattened mapped data
        $this->form->fill($formData);

        // Show notification
        \Filament\Notifications\Notification::make()
            ->title('Teacher Data Loaded')
            ->body('Legacy teacher data has been auto-filled. Please review and complete required fields.')
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
