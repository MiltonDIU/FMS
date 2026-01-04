<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeacherProfileStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public ?Teacher $record = null;

    protected function getTeacher(): ?Teacher
    {
        if ($this->record) {
            return $this->record;
        }
        return Auth::user()?->teacher;
    }

    public static function canView(): bool
    {
        return Auth::user()?->can('View:TeacherProfileStatsWidget');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $teacher = $this->getTeacher();

        if (! $teacher) {
            return [];
        }

        return [
            Stat::make('Total Education', $teacher->educations()->count())
                ->description('Degrees Listed')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Publications', $teacher->publications()->count())
                ->description('Research Items')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),

            Stat::make('Research Projects', $teacher->researchProjects()->count())
                ->description('Projects Led/Joined')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('info'),

            Stat::make('Job Experience', $teacher->jobExperiences()->count())
                ->description('Roles Held')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),

            Stat::make('Trainings', $teacher->trainingExperiences()->count())
                ->description('Programs Attended')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('primary'),

            Stat::make('Certifications', $teacher->certifications()->count())
                ->description('Professional Certs')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Skills', $teacher->skills()->count())
                ->description('Listed Skills')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),

            Stat::make('Awards', $teacher->awards()->count())
                ->description('Honors Received')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),

            Stat::make('Teaching Areas', $teacher->teachingAreas()->count())
                ->description('Subjects')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('primary'),

            Stat::make('Memberships', $teacher->memberships()->count())
                ->description('Organizations')
                ->descriptionIcon('heroicon-m-identification')
                ->color('success'),

            Stat::make('Social Links', $teacher->socialLinks()->count())
                ->description('Profiles Linked')
                ->descriptionIcon('heroicon-m-link')
                ->color('gray'),
        ];
    }
}
