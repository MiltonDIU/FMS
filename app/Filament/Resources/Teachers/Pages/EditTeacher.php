<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\HasWindowedRepeaters;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    use HasWindowedRepeaters;

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
            
            if (isset($data['photo'])) {
                $photoComponent = collect($this->form->getFlatComponents())
                    ->first(function ($c) {
                        return method_exists($c, 'getName') && $c->getName() === 'photo';
                    });

                if ($photoComponent) {
                    $photoComponent->saveRelationships();
                }
            }

            $this->record->refresh();
            if ($this->record->hasMedia('avatar')) {
                $avatarUrl = $this->record->getFirstMediaUrl('avatar');
                if ($avatarUrl) {
                    \App\Services\TeacherVersionService::$ignoreObserver = true;
                    $this->record->updateQuietly(['photo' => $avatarUrl]);
                }
            }

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
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
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

        // Unset photo so SpatieMediaLibraryFileUpload component can load media directly from model
        unset($data['photo']);

        $data = $this->mergeHrProfile($data);

        return $data;
    }

    /**
     * Overlay the HR system's version of this teacher onto the loaded form.
     *
     * Reached from the search box on the create screen, which sends anyone
     * already on file here rather than trying to create them twice. Nothing is
     * written: the form is filled so the changes can be looked at and saved —
     * or abandoned — by whoever asked for the merge.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mergeHrProfile(array $data): array
    {
        $employeeId = request()->query('hrMerge');

        // Only for the record actually asked for, so a stale or hand-edited
        // query string cannot pull one teacher's profile onto another.
        if (blank($employeeId) || (string) $employeeId !== (string) $this->record->employee_id) {
            return $data;
        }

        try {
            $profile = app(\App\Services\HrApiService::class)->getTeacherProfile((string) $employeeId);
        } catch (\RuntimeException $e) {
            \Filament\Notifications\Notification::make()
                ->title('Could not load the HR profile')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return $data;
        }

        if ($profile === null) {
            \Filament\Notifications\Notification::make()
                ->title('No HR profile found')
                ->body("The directory has nothing for employee {$employeeId}.")
                ->warning()
                ->send();

            return $data;
        }

        $slug = (string) \App\Models\Setting::get('teacher_integration_mapping', 'erp_teacher_profile');
        $overview = app(\App\Services\IntegrationService::class)->transform($profile, $slug);

        // Passing the record keeps its address, publication state and listing
        // position out of the payload's hands.
        $incoming = \App\Helpers\FormPayloadResolver::resolveForForm($overview, $this->record);

        $changed = $this->applyScalars($data, $incoming);
        $counts = $this->applyRelations($data, $incoming);

        \Filament\Notifications\Notification::make()
            ->title('HR data merged into the form')
            ->body("{$changed} field(s) updated, {$counts['updated']} detail row(s) refreshed, "
                . "{$counts['added']} added. Nothing was removed. Review and press Save to keep it.")
            ->success()
            ->persistent()
            ->send();

        return $data;
    }

    /**
     * Overlay the teacher's own columns, leaving anything the API is silent on.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $incoming
     * @return int how many fields actually changed
     */
    protected function applyScalars(array &$data, array $incoming): int
    {
        $changed = 0;

        foreach ($incoming as $key => $value) {
            if (is_array($value) || $value === null || $value === '') {
                continue;
            }

            // email is the account's, handled separately on save.
            if ($key === 'email') {
                continue;
            }

            if (($data[$key] ?? null) != $value) {
                $data[$key] = $value;
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Merge each repeated section, matching rows rather than replacing them.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $incoming
     * @return array{updated:int,added:int}
     */
    protected function applyRelations(array &$data, array $incoming): array
    {
        $updated = $added = 0;

        foreach (array_keys(\App\Support\RelationMerge::MATCH_ON) as $relation) {
            $rows = $incoming[$relation] ?? null;

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $result = \App\Support\RelationMerge::mergeRows(
                is_array($data[$relation] ?? null) ? $data[$relation] : [],
                $rows,
                \App\Support\RelationMerge::keysFor($relation),
            );

            $data[$relation] = $result['rows'];
            $updated += $result['updated'];
            $added += $result['added'];
        }

        return ['updated' => $updated, 'added' => $added];
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
