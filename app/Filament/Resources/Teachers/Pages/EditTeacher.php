<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\HasWindowedRepeaters;
use App\Filament\Concerns\SavesTeacherProfile;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    use HasWindowedRepeaters;
    use SavesTeacherProfile;

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

    /**
     * Leave validation to the server.
     *
     * With the browser's own checks on, an invalid field on a tab that is not
     * showing — an imported secondary email that is not an address, a job
     * with no start date — blocks the submit before it is sent, and the
     * browser's message bubble has nowhere visible to appear. Save then does
     * nothing at all, as if the button had not been pressed. The server runs
     * the same rules and says exactly which tab and row to fix.
     */
    public function getFormContentComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getFormContentComponent()->extraAttributes(['novalidate' => true]);
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
            $data = $this->validatedStateWithoutSaving();

            if ($data === null) {
                return;
            }

            // Handle specific overrides from mutateFormDataBeforeSave
            // Note: Filament internally calls mutateFormDataBeforeSave inside save() typically,
            // but since we are overriding, we must handle it or pass raw data to service
            // and let service handle logic.
            // However, mutateFormDataBeforeSave in this class modifies User Email.
            // We should run that logic first.
            $data = $this->mutateFormDataBeforeSave($data);

            $data = array_merge($data, $this->relationStateForService());

            /** @var \App\Services\TeacherVersionService $service */
            $service = app(\App\Services\TeacherVersionService::class);

            $lastVersionId = (int) \App\Models\TeacherVersion::where('teacher_id', $this->record->id)->max('id');

            // Direct update, or a version waiting for approval — unless an
            // administrator is making the change, in which case it applies now.
            // The "full snapshot" box is not a teacher column: read it from
            // the form, and only for an editor whose changes apply directly.
            $fullSnapshot = $this->canSaveFullSnapshot() && (bool) data_get($this->data, 'save_full_snapshot');

            $changed = $service->handleUpdateFromForm($this->record, $data, $this->editorSkipsApproval(), $fullSnapshot);

            // A restore point asked for on a save that changed nothing.
            if ($fullSnapshot && ! $changed) {
                $service->recordFullSnapshot($this->record, $data);
            }

            $pending = \App\Models\TeacherVersion::where('teacher_id', $this->record->id)
                ->where('id', '>', $lastVersionId)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            $this->saveTeacherMedia($this->record);

            // Back to what is stored. Rows added on this save only have
            // placeholder keys in the form, so saving again without a refill
            // would add them a second time; and changes waiting for approval
            // should not look as if they had already been made.
            $this->fillForm();

            // Say which of the three things happened. "Saved or submitted for
            // approval" left an administrator unable to tell a change that took
            // effect from one sitting in a queue.
            if ($pending) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Submitted for approval — not live yet')
                    ->body('Waiting for approval: ' . implode(', ', (array) $pending->pending_sections)
                        . '. The profile keeps its current values until this is approved.')
                    ->persistent()
                    ->send();
            } elseif ($changed || $fullSnapshot) {
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title($changed ? 'Profile saved' : 'Full snapshot saved')
                    ->body($fullSnapshot ? 'A full snapshot of the profile was kept as a restore point.' : null)
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->info()
                    ->title('No changes to save')
                    ->send();
            }

            if ($shouldRedirect && ($redirectUrl = $this->getRedirectUrl())) {
                $this->redirect($redirectUrl);
            }
            
        } catch (\Filament\Support\Exceptions\Halt $exception) {
            return;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->explainValidationErrors($exception);

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
     * Administrators' edits take effect at once; approval is for a teacher's
     * own changes. Someone editing their own teacher record still goes
     * through approval, whatever their role.
     */
    protected function editorSkipsApproval(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole(['super_admin', 'admin'])
            && $this->record->user_id !== $user->id;
    }

    /**
     * Whether this editor may keep a save as a full snapshot — a restore
     * point the profile can later be rolled back to. Offered where changes
     * apply directly; a teacher's own submissions already keep the whole form.
     */
    public function canSaveFullSnapshot(): bool
    {
        return $this->editorSkipsApproval();
    }

    /** Headings for the repeater tabs, as they read on the form. */
    /** Plain-field labels for the error summary, where the message alone is vague. */
    protected const FIELD_TABS = [
        'secondary_email' => 'Contact Info',
        'phone' => 'Contact Info',
        'personal_phone' => 'Contact Info',
        'webpage' => 'Basic Info',
        'employee_id' => 'Basic Info',
        'first_name' => 'Basic Info',
        'last_name' => 'Basic Info',
        'department_id' => 'Basic Info',
        'designation_id' => 'Basic Info',
    ];

    protected const SECTION_LABELS = [
        'educations' => 'Educations',
        'publications' => 'Publications',
        'jobExperiences' => 'Job Experience',
        'trainingExperiences' => 'Training Experience',
        'awards' => 'Awards',
        'skills' => 'Skills',
        'teachingAreas' => 'Teaching Areas',
        'researchInterests' => 'Research Interest',
        'areasOfExpertise' => 'Area of Expertise',
        'memberships' => 'Memberships',
        'socialLinks' => 'Social Links',
    ];

    /**
     * Say what stopped the save, and where, at the top of the page.
     *
     * The form marks the offending fields, but they are usually on another
     * tab — often a row imported from the old system with a blank Degree Type
     * or Start Date — so a change on the Settings tab looked as if it had
     * saved when nothing had. 215 of the 1,208 active teachers carry such a
     * row. This lists each problem as tab, row and message.
     */
    protected function explainValidationErrors(\Illuminate\Validation\ValidationException $exception): void
    {
        $lines = [];

        foreach ($exception->errors() as $key => $messages) {
            $parts = explode('.', (string) $key);
            $message = $messages[0] ?? 'Invalid value.';

            if (($parts[0] ?? null) === 'data' && isset(self::SECTION_LABELS[$parts[1] ?? ''], $parts[2])) {
                $rows = array_keys((array) data_get($this->data, $parts[1], []));
                $position = array_search($parts[2], $rows, true);
                $row = $position === false ? '' : ' — row ' . ($position + 1);

                $lines[] = self::SECTION_LABELS[$parts[1]] . $row . ': ' . $message;
            } elseif (isset(self::FIELD_TABS[$parts[1] ?? ''])) {
                $lines[] = self::FIELD_TABS[$parts[1]] . ': ' . $message;
            } else {
                $lines[] = $message;
            }
        }

        $lines = array_values(array_unique($lines));
        $shown = array_slice($lines, 0, 8);

        \Filament\Notifications\Notification::make()
            ->danger()
            ->title('Not saved — ' . count($lines) . ' field(s) need fixing')
            ->body(new \Illuminate\Support\HtmlString(
                implode('<br>', array_map('e', $shown))
                . (count($lines) > count($shown) ? '<br>… and ' . (count($lines) - count($shown)) . ' more' : '')
                . '<br><br>Open the tab named above, fill in the field, then save again.'
            ))
            ->persistent()
            ->send();
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
