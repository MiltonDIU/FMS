<?php

use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\Auth\TeacherActivationController;
use App\Http\Controllers\Auth\TeacherPasswordSetupController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\DepartmentController;
use App\Http\Controllers\Frontend\TeacherController;
use App\Http\Controllers\Frontend\PublicationController;
use App\Http\Controllers\ThemeScreenshotController;
use App\Http\Middleware\EnsureThemeInstalled;
use Illuminate\Support\Facades\Route;

// Legacy teacher search for the admin "Create Teacher" page. Registered here
// rather than in routes/api.php because the browser calls it with the panel's
// session cookie, and the "api" middleware group is stateless. Must stay above
// the frontend catch-all routes below, which would otherwise swallow the path.
Route::middleware('auth')->group(function () {
    Route::get('/api/teacher/search', [TeacherApiController::class, 'search']);

    // A theme's screenshot lives inside its own folder, which is not web-served.
    // Above the frontend catch-all for the same reason as the search route.
    Route::get('/admin/theme-screenshot/{theme}', ThemeScreenshotController::class)
        ->name('theme.screenshot');
});

/*
|--------------------------------------------------------------------------
| Teacher activation
|--------------------------------------------------------------------------
| Every teacher account came over from the old system with an unusable
| password, so this emailed link is the only way in. It is the one route that
| grants a session without a password, hence the throttle: the token is 64
| random characters and unguessable, but the endpoint should not be an open
| door to hammer either.
|
| Both paths sit above the frontend catch-all, which would otherwise match
| /teacher/... as a faculty and department.
*/
Route::middleware('throttle:10,1')
    ->get('/teacher/activate/{token}', TeacherActivationController::class)
    ->name('teacher.activate');

/*
|--------------------------------------------------------------------------
| Email tracking
|--------------------------------------------------------------------------
| The pixel and the link redirect that tell the Email Batches screen who has
| read a message. Both are opened by mail clients rather than by people, so
| neither is authenticated and neither can be, and both sit above the frontend
| catch-all that would otherwise match them.
|
| The throttle is generous on purpose: one recipient's client can fetch the
| pixel several times, and a whole faculty can sit behind one institutional
| proxy address.
*/
Route::middleware('throttle:240,1')->prefix('email-track')->group(function () {
    Route::get('/open/{token}.gif', [EmailTrackingController::class, 'open'])
        ->name('email.track.open');

    Route::get('/click/{token}', [EmailTrackingController::class, 'click'])
        ->name('email.track.click');
});

Route::middleware('auth')->group(function () {
    Route::get('/teacher/set-password', [TeacherPasswordSetupController::class, 'create'])
        ->name('teacher.password.create');

    Route::post('/teacher/set-password', [TeacherPasswordSetupController::class, 'store'])
        ->name('teacher.password.store');
});

/*
| Public nested frontend routes (Blade + Livewire monolith, placed at the bottom).
|
| Grouped behind EnsureThemeInstalled so that deleting every theme folder answers
| 503 with an explanation rather than rendering an empty-looking 200. The admin
| panel sits outside this group on purpose — it is where the fix happens.
*/
Route::middleware(EnsureThemeInstalled::class)->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/{faculty_short_name}', [HomeController::class, 'index'])->name('faculty.show');
    Route::get('/{faculty_short_name}/{department_code}', [DepartmentController::class, 'show'])->name('department.show');
    Route::get('/{faculty_short_name}/{department_code}/contact', [DepartmentController::class, 'contact'])->name('department.contact');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}', [TeacherController::class, 'show'])->name('teacher.show');
    // The card social networks show when a profile is shared. Public: the only
    // things that fetch it are crawlers, and they arrive without a session.
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/share-image.png', [TeacherController::class, 'shareImage'])->name('teacher.share-image');
    Route::middleware('throttle:30,1')->get('/{faculty_short_name}/{department_code}/{teacher_webpage}/vcard', [TeacherController::class, 'vcard'])->name('teacher.vcard');
    Route::middleware('throttle:30,1')->get('/{faculty_short_name}/{department_code}/{teacher_webpage}/cv', [TeacherController::class, 'cv'])->name('teacher.cv');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/publication/{publication_slug}', [PublicationController::class, 'show'])->name('publication.show');
});
