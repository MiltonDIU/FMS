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

        /*
         * Header statistics, shared with every theme's header partial so the
         * view no longer runs database queries itself.
         *
         * All three count only what a visitor can actually reach. The teacher
         * count always did; the other two counted every row, so the header
         * advertised six faculties while the page beneath it listed five — the
         * sixth being "System - Unassigned Faculty", the holding pen for
         * teachers with no department set, switched off precisely so it is not
         * shown.
         *
         * Departments carry the faculty condition as well, because a department
         * is only reachable when its faculty is published: DepartmentController
         * resolves the faculty first and answers 404 when it is not. One
         * department sits under that unassigned faculty.
         *
         * The conditions match what HomeController counts, so a number in the
         * header and the same number on the page cannot disagree.
         */
        View::composer('frontend.themes.*.partials.header', function ($view) {
            $view->with([
                'facultiesCount' => Faculty::where('is_active', true)->count(),
                'departmentsCount' => Department::where('is_active', true)
                    ->whereHas('faculty', fn ($query) => $query->where('is_active', true))
                    ->count(),
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

        /*
         * Media belongs to the library, so it cannot register its own observer
         * the way our models do in booted(). Registered here instead, and it has
         * to be registered somewhere: without it nothing stamps the year a
         * teacher's file is filed under, and TeacherMediaPathGenerator falls
         * back to reading the joining date on every path it builds.
         */
        \Spatie\MediaLibrary\MediaCollections\Models\Media::observe(\App\Observers\MediaObserver::class);
    }
}
