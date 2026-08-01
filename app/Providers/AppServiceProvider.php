<?php

namespace App\Providers;

use App\Filament\Pages\TeacherDashboard;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Teacher;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        /*
         * The audit trail's policy is registered by hand. Laravel guesses
         * App\Models\Foo -> App\Policies\FooPolicy, and this model has to be
         * named explicitly because config/activitylog.php decides which class
         * the package actually uses. With strictAuthorization() on, a missing
         * policy raises rather than quietly allowing — so this is not optional.
         */
        Gate::policy(\App\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        // Share header statistics with every theme's header partial so the
        // view no longer runs database queries itself.
        View::composer('frontend.themes.*.partials.header', function ($view) {
            $view->with([
                'facultiesCount' => Faculty::count(),
                'departmentsCount' => Department::count(),
                'teachersCount' => Teacher::where('is_active', true)->where('is_archived', false)->count(),
            ]);
        });

        // Register custom route for TeacherDashboard with teacher ID parameter
        // This allows URLs like: /admin/teacher-dashboard/5
        if (app()->runningInConsole() === false) {
            Route::middleware(['web', 'auth'])
                ->prefix('admin')
                ->group(function () {
                    Route::get(
                        '/teacher-dashboard/{teacher}',
                        TeacherDashboard::class
                    )->name('filament.admin.pages.teacher-dashboard.view');
                });
        }

        // Dynamic Mail Configuration
        MailConfigService::configure();

        // Register Livewire components explicitly (auto-discovery provider not wired up).
        Livewire::component('teacher-search', \App\Livewire\Frontend\TeacherSearch::class);
        Livewire::component('department-search', \App\Livewire\Frontend\DepartmentSearch::class);
    }
}
