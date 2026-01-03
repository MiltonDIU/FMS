<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeacherQuickActionsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7; // Top priority

    /**
     * Visible only to Teachers
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['teacher', 'super_admin']) || Auth::user()?->can('view_teacher_dashboard');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Update Profile', 'Edit Information')
                ->description('Request changes to your profile')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('primary')
                // Assuming route for profile edit exists or using resource edit
                ->url(route('filament.admin.pages.my-profile')),

            Stat::make('Add Publication', 'New Research')
                ->description('Submit a new publication')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('success')
                ->url(route('filament.admin.resources.publications.create')),

            /*
            Stat::make('Add Project', 'New Grant')
                ->description('Register a research project')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('warning')
                ->url(route('filament.admin.resources.research-projects.create')),
            */
        ];
    }
}
