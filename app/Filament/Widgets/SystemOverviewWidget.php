<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Publication;
use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class SystemOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
//        return auth()->user()?->hasRole('super_admin') ?? false;
        return Auth::user()?->can('View:SystemOverviewWidget');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Faculties', Faculty::count())
                ->description('Academic Faculties')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Departments', Department::count())
                ->description('Academic Departments')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),

            Stat::make('Total Teachers', Teacher::where('is_archived', false)->count())
                ->description('Active Profile')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Total Publications', Publication::count())
                ->description('Research Items')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Total Roles', Role::count())
                ->description('System Roles')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('danger'),
        ];
    }
}
