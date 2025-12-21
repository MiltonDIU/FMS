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
        return auth()->user()->hasRole('teacher') || auth()->user()->can('page_MyProfile');
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
            $data = $this->form->getState();
            
            $teacher = auth()->user()->teacher;
            
            if ($teacher) {
                $teacher->update($data);
                
                // Save relationships (Repeaters)
                $this->form->model($teacher)->saveRelationships();
                
                Notification::make()
                    ->success()
                    ->title(__('Profile saved successfully'))
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title(__('Profile not found. Please contact administrator.'))
                    ->send();
            }
        } catch (Halt $exception) {
            return;
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
