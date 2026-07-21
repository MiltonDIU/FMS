<?php

namespace App\Filament\Resources\Teachers\Widgets;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TeacherVerificationStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTeachers = Teacher::count();
        $verifiedCount = Teacher::where('verification_status', 'verified')->count();
        $pendingCount = Teacher::where('verification_status', 'pending_verification')->count();
        $unverifiedCount = Teacher::where(function ($q) {
            $q->whereIn('verification_status', ['unverified', 'correction_requested'])
              ->orWhereNull('verification_status');
        })->count();

        return [
            Stat::make('Total Teachers', $totalTeachers)
                ->description('Imported faculty members')
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('Verified Profiles', $verifiedCount)
                ->description('Confirmed data accuracy')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Pending Verification', $pendingCount)
                ->description('Emails sent, awaiting teacher confirmation')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Unverified / Action Needed', $unverifiedCount)
                ->description('Verification email not sent or needs edit')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
