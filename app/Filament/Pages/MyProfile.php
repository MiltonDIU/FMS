<?php

namespace App\Filament\Pages;

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
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.my-profile';

    protected static ?string $slug = 'my-profile';

    protected static ?string $title = 'My Profile';

    public static function canAccess(): bool
    {
        // Allow access if user has 'teacher' role OR has specific permission
        // This handles cases where a user might have multiple roles
        return  auth()->user()->can('View:MyProfile') & auth()->user()?->hasRole('teacher') ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $teacher = auth()->user()->teacher;

        if ($teacher) {
            $formData = $teacher->load([
                'educations',
                'publications',
                'jobExperiences',
                'awards',
                'skills',
                'teachingAreas',
                'socialLinks',
            ])->toArray();

            // Add user email for display
            $formData['email'] = auth()->user()->email;

            $this->form->fill($formData);
        }
    }

    public function form(Schema $schema): Schema
    {
        return TeacherForm::configure($schema, isOwnProfile: true)
            ->statePath('data')
            ->model(Teacher::class);
    }

    public function save(): void
    {
        try {
            // IMPORTANT: Use $this->data instead of getState() 
            // because Filament stores repeater relationship data in the Livewire component state
            $data = $this->data;
            
            // DEBUG: Log raw Livewire data
            \Log::info('MyProfile: Raw Livewire data ($this->data)', [
                'keys' => array_keys($data),
                'has_skills' => isset($data['skills']),
                'has_educations' => isset($data['educations']),
                'skills_count' => isset($data['skills']) ? count($data['skills']) : 0,
                'educations_count' => isset($data['educations']) ? count($data['educations']) : 0,
            ]);

            $teacher = auth()->user()->teacher;

            if (!$teacher) {
                Notification::make()
                    ->danger()
                    ->title(__('Profile not found. Please contact administrator.'))
                    ->send();
                return;
            }

            // Normalize relation arrays - Filament repeaters use UUID keys
            $relationNames = ['educations', 'publications', 'jobExperiences', 'trainingExperiences', 
                              'awards', 'skills', 'teachingAreas', 'memberships', 'socialLinks'];
            
            foreach ($relationNames as $relationName) {
                if (isset($data[$relationName]) && is_array($data[$relationName])) {
                    // Convert keyed array to indexed array (remove UUID keys)
                    $data[$relationName] = array_values($data[$relationName]);
                    
                    \Log::info("MyProfile: Normalized {$relationName}", [
                        'count' => count($data[$relationName]),
                        'sample' => !empty($data[$relationName]) ? array_keys($data[$relationName][0] ?? []) : [],
                    ]);
                }
            }

            // Use TeacherVersionService for proper approval workflow
            /** @var \App\Services\TeacherVersionService $service */
            $service = app(\App\Services\TeacherVersionService::class);
            
            // This handles:
            // 1. Direct update if no approval needed
            // 2. Version creation if approval needed
            $service->handleUpdateFromForm($teacher, $data);

            Notification::make()
                ->success()
                ->title(__('Profile update submitted successfully'))
                ->body(__('Your changes have been submitted for approval.'))
                ->send();
                
        } catch (Halt $exception) {
            return;
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

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Changes'))
                ->submit('save'),
        ];
    }
}
