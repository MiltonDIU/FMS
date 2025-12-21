<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;

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
}
