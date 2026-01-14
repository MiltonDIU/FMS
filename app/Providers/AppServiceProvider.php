<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom route for TeacherDashboard with teacher ID parameter
        // This allows URLs like: /admin/teacher-dashboard/5
        if (app()->runningInConsole() === false) {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
                ->prefix('admin')
                ->group(function () {
                    \Illuminate\Support\Facades\Route::get(
                        '/teacher-dashboard/{teacher}',
                        \App\Filament\Pages\TeacherDashboard::class
                    )->name('filament.admin.pages.teacher-dashboard.view');
                });
        }

        // Dynamic Mail Configuration
        \App\Services\MailConfigService::configure();
}
}
