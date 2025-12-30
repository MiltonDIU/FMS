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

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    /**
     * Override the save method to handle approvals.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->authorizeAccess();

        try {
            // Get raw form data including relationships
            $data = $this->form->getState();
            
            // Handle specific overrides from mutateFormDataBeforeSave
            // Note: Filament internally calls mutateFormDataBeforeSave inside save() typically,
            // but since we are overriding, we must handle it or pass raw data to service 
            // and let service handle logic.
            // However, mutateFormDataBeforeSave in this class modifies User Email.
            // We should run that logic first.
            $data = $this->mutateFormDataBeforeSave($data);
            
            // Call our service
            /** @var \App\Services\TeacherVersionService $service */
            $service = app(\App\Services\TeacherVersionService::class);
            
            // This method handles:
            // 1. Direct update if no approval needed
            // 2. Version creation if approval needed (and stopping direct update)
            $service->handleUpdateFromForm($this->record, $data);
            
            // If we are here, it means success.
            // If a version was created pending approval, we should notify the user.
            // If direct update happened, we notify saved.
            
            // How do we know result? 
            // Ideally service returns a status enum or object. 
            // For now, let's assume if it didn't throw, it's good.
            // We can check if a pending status version was just created?
            // Or roughly check recent versions.
            
            // Simple generic notification
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Profile updated successfully or submitted for approval.')
                ->send();

            if ($shouldRedirect && ($redirectUrl = $this->getRedirectUrl())) {
                $this->redirect($redirectUrl);
            }
            
        } catch (\Filament\Support\Exceptions\Halt $exception) {
            return;
        } catch (\Exception $exception) {
             \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Error updating profile')
                ->body($exception->getMessage())
                ->send();
        }
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
