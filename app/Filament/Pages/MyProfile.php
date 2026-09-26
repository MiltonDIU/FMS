<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasWindowedRepeaters;
use App\Filament\Concerns\SavesTeacherProfile;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class MyProfile extends Page
{
    use HasWindowedRepeaters;
    use SavesTeacherProfile;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.my-profile';

    protected static ?string $slug = 'my-profile';

    protected static ?string $title = 'My Profile';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Both conditions are wanted here, unlike the other pages: the screen
        // edits the signed-in user's own teacher record, so without one there is
        // nothing to show. Checked against the actual relation rather than the
        // "teacher" role, since that is what mount() reads.
        return $user->can('View:MyProfile') && $user->isTeacher();
    }

    public ?array $data = [];
    public array $gapReport = [];

    public function mount(): void
    {
        $teacher = auth()->user()?->teacher;

        if ($teacher) {
            $evaluator = new \App\Services\ProfileGapEvaluator();
            $this->gapReport = $evaluator->evaluate($teacher);

            $formData = $teacher->load([
                'educations',
                'publications.teachers',
                'jobExperiences',
                'trainingExperiences',
                'awards',
                'skills',
                'teachingAreas',
                'memberships',
                'socialLinks',
            ])->toArray();

            // Manually populate author fields for the repeater
            if (isset($formData['publications']) && is_array($formData['publications'])) {
                foreach ($formData['publications'] as $key => $publication) {
                    $teachers = collect($publication['teachers'] ?? []);

                    $formData['publications'][$key]['first_author_id'] = $teachers
                        ->first(fn ($t) => ($t['pivot']['author_role'] ?? '') === 'first')['id'] ?? null;

                    $formData['publications'][$key]['corresponding_author_id'] = $teachers
                        ->first(fn ($t) => ($t['pivot']['author_role'] ?? '') === 'corresponding')['id'] ?? null;

                    $formData['publications'][$key]['co_author_ids'] = $teachers
                        ->filter(fn ($t) => ($t['pivot']['author_role'] ?? '') === 'co_author')
                        ->sortBy(fn ($t) => $t['pivot']['sort_order'] ?? 0)
                        ->pluck('id')
                        ->toArray();
                }
            }

            // Add user email for display
            $formData['email'] = auth()->user()->email;

            // Unset scalar photo field so SpatieMediaLibraryFileUpload loads avatar media directly from model
            unset($formData['photo']);

            $this->form->fill($formData);
        }
    }

    public function form(Schema $schema): Schema
    {
        $teacher = auth()->user()->teacher;

        return TeacherForm::configure($schema, isOwnProfile: true)
            ->statePath('data')
            ->model($teacher ?? Teacher::class);
    }

    public function save(): void
    {
        try {
            $teacher = auth()->user()->teacher;

            if (!$teacher) {
                Notification::make()
                    ->danger()
                    ->title(__('Profile not found. Please contact administrator.'))
                    ->send();
                return;
            }

            // Validated and dehydrated, not the raw Livewire state this used
            // to read: that skipped validation, carried the locked fields
            // (department, designation, job type) at whatever the browser
            // sent, and gave uploads to the service as the widget's arrays.
            $data = $this->validatedStateWithoutSaving();

            if ($data === null) {
                return;
            }

            unset($data['email']);

            $data = array_merge($data, $this->relationStateForService());

            // Use TeacherVersionService for proper approval workflow
            /** @var \App\Services\TeacherVersionService $service */
            $service = app(\App\Services\TeacherVersionService::class);

            // This handles:
            // 1. Direct update if no approval needed
            // 2. Version creation if approval needed
            $service->handleUpdateFromForm($teacher, $data);

            $this->saveTeacherMedia($teacher);

            // Saving is not confirming. Verification used to be set here on
            // every save, so pressing Save once counted as a declaration the
            // teacher never made; it is now only the declaration panel's job.

            // Back to what is stored: rows added on this save only have
            // placeholder keys, so a second save would add them again.
            $this->mount();

            Notification::make()
                ->success()
                ->title(__('Profile update submitted successfully'))
                ->body(__('Your changes have been saved.'))
                ->send();

        } catch (Halt $exception) {
            return;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            \Log::error('MyProfile save error: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString()
            ]);

            Notification::make()
                ->danger()
                ->title(__('Error updating profile'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    /**
     * The declaration checkbox. Nothing is stored until the teacher confirms.
     */
    public bool $declarationAccepted = false;

    /**
     * Whether the declaration panel is shown: until the teacher has confirmed
     * once, and never again after.
     */
    public function getNeedsDeclarationProperty(): bool
    {
        $teacher = auth()->user()?->teacher;

        return $teacher !== null && $teacher->verification_status !== 'verified';
    }

    /**
     * The teacher's own statement that their profile is complete and correct.
     *
     * Separate from saving the profile, and only with the declaration ticked —
     * checked here as well as on the button, since the button's disabled state
     * is only the browser's. Once made it is not asked for again.
     */
    public function confirmVerification(): void
    {
        $teacher = auth()->user()?->teacher;

        if (! $teacher || $teacher->verification_status === 'verified') {
            return;
        }

        if (! $this->declarationAccepted) {
            Notification::make()
                ->warning()
                ->title(__('Please tick the box to confirm'))
                ->body(__('Kindly tick the confirmation box once you have reviewed your profile.'))
                ->send();

            return;
        }

        $teacher->markAsVerified();
        $this->declarationAccepted = false;

        Notification::make()
            ->success()
            ->title(__('Thank you!'))
            ->body(__('Your profile has been confirmed and is ready for the faculty directory.'))
            ->send();

        // Refresh Livewire component state
        $this->mount();
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Changes'))
                ->submit('save'),
        ];
    }
}
