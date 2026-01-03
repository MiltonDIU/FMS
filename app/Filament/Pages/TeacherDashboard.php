<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TeacherProfessionalInfoWidget;
use App\Filament\Widgets\TeacherProfileCompletionWidget;
use App\Filament\Widgets\TeacherProfileStatsWidget;
use App\Filament\Widgets\TeacherPublicationTrendWidget;
use App\Filament\Widgets\TeacherPublicationsStatsWidget;
use App\Filament\Widgets\TeacherQuickActionsWidget;
use App\Filament\Widgets\TeacherResearchStatsWidget;
use App\Models\Teacher;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TeacherDashboard extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public function getView(): string
    {
        return 'filament.pages.teacher-dashboard';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teacher Dashboard';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Dashboards';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from navigation menu - accessed via Teacher list instead
        return false;
    }

    public function getTitle(): string
    {
        $teacher = $this->getSelectedTeacher();
        
        if ($teacher) {
            return 'Dashboard - ' . $teacher->full_name;
        }
        
        return 'Teacher Dashboard';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Allow super_admin
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Allow teachers
        if ($user->hasRole('teacher')) {
            return true;
        }

        // Allow users with specific permission
        if ($user->can('view_teacher_dashboard')) {
            return true;
        }

        return false;
    }

    public ?int $teacherId = null;

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        // Override to support route parameters
        if (isset($parameters['teacher'])) {
            $teacherId = $parameters['teacher'];
            // Build URL manually: /admin/teacher-dashboard/5
            return url('/admin/teacher-dashboard/' . $teacherId);
        }
        
        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }

    public function mount(?int $teacher = null): void
    {
        $user = Auth::user();
        
        // Teachers ALWAYS see their own dashboard, ignore URL parameter
        if ($user?->isTeacher()) {
            $this->teacherId = $user->teacher?->id;
            return;
        }

        // Admins can view specific teacher from route parameter
        if ($teacher) {
            // Verify the teacher exists
            $teacherModel = Teacher::find($teacher);
            if ($teacherModel) {
                $this->teacherId = $teacher;
            }
        }
    }

    protected function getSelectedTeacher(): ?Teacher
    {
        if ($this->teacherId) {
            return Teacher::find($this->teacherId);
        }
        
        return null;
    }

    protected function getHeaderWidgets(): array
    {
        $teacher = $this->getSelectedTeacher();
        
        if (!$teacher) {
            return [];
        }
        
        return [
            TeacherProfileStatsWidget::make(['record' => $teacher]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        $teacher = $this->getSelectedTeacher();
        
        if (!$teacher) {
            return [];
        }
        
        return [
            TeacherProfileCompletionWidget::make(['record' => $teacher]),
            TeacherProfessionalInfoWidget::make(['record' => $teacher]),
            TeacherPublicationsStatsWidget::make(['record' => $teacher]),
            TeacherResearchStatsWidget::make(['record' => $teacher]),
            TeacherPublicationTrendWidget::make(['record' => $teacher]),
            TeacherQuickActionsWidget::make(['record' => $teacher]),
        ];
    }
}
