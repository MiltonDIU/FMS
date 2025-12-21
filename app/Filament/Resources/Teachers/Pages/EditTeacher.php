<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Fill form with user email for display.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add user email for display
        if ($this->record->user) {
            $data['email'] = $this->record->user->email;
        }
        
        return $data;
    }

    /**
     * Update user email if admin changed it.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update user email if provided and changed
        if (isset($data['email']) && $this->record->user) {
            $this->record->user->update(['email' => $data['email']]);
        }
        
        // Remove email from data as it's not a Teacher column
        unset($data['email']);
        
        return $data;
    }
}
