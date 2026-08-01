<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\PublicationController;
use App\Http\Controllers\Api\V1\TeacherProfileController;
use App\Http\Controllers\Frontend\TeacherController;
use App\Http\Middleware\RequirePasswordChangeApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
| The read API for the mobile directory apps the departments are building.
|
| Versioned from the first day. An app that has shipped cannot be asked to
| update in step with the server, so /v1 has to keep answering the way it does
| today even when a /v2 exists.
|
| The website itself uses none of this — it is a Blade + Livewire monolith
| served from routes/web.php, and this group is stateless, so a browser's panel
| session means nothing here.
|
| Everything but signing in needs a token. The same information is public on the
| website, so this is not secrecy: it is knowing which app is asking, so a
| misbehaving one can be cut off without taking the website down with it. And it
| is what makes the forced first password change reachable at all — an app that
| never signs in could never be told to replace the password it was emailed.
|
| Teacher::published() is what keeps the 872 archived and inactive records out of
| every answer, applied inside the controllers rather than here, so a new
| endpoint cannot forget it.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Signing in
    |----------------------------------------------------------------------
    | The only endpoints reachable without a token, and the only ones where
    | guessing repeatedly gains anything — hence the tight limit. Six a
    | minute is far above what a person mistyping needs and far below what
    | working through a password list requires.
    */
    Route::middleware('throttle:6,1')->prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
    });

    /*
    |----------------------------------------------------------------------
    | Everything else
    |----------------------------------------------------------------------
    | auth:sanctum reads the bearer token; RequirePasswordChangeApi then
    | refuses everything except me / password.change / logout while the
    | account is still on the password that was emailed to it.
    |
    | 120 requests a minute per token: a profile screen opening several
    | sections at once is nowhere near it, and a loop is.
    */
    Route::middleware(['auth:sanctum', 'throttle:120,1', RequirePasswordChangeApi::class])->group(function () {

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('password/change', [AuthController::class, 'changePassword'])->name('password.change');
        });

        // ── Directory ────────────────────────────────────────────────────
        Route::get('faculties', [DirectoryController::class, 'faculties'])->name('faculties.index');
        Route::get('faculties/{faculty}', [DirectoryController::class, 'faculty'])->name('faculties.show');
        Route::get('faculties/{faculty}/departments', [DirectoryController::class, 'departments'])->name('departments.index');
        Route::get('faculties/{faculty}/departments/{department}', [DirectoryController::class, 'department'])->name('departments.show');
        Route::get('faculties/{faculty}/departments/{department}/teachers', [DirectoryController::class, 'departmentTeachers'])->name('departments.teachers');

        // Flat across every faculty — an app builds a filter from this in one
        // request instead of one per faculty.
        Route::get('departments', [DirectoryController::class, 'allDepartments'])->name('departments.all');

        Route::get('teachers', [DirectoryController::class, 'teachers'])->name('teachers.index');

        Route::get('publications', [PublicationController::class, 'index'])->name('publications.index');

        Route::get('lookups', [LookupController::class, 'index'])->name('lookups.index');

        /*
        |------------------------------------------------------------------
        | One teacher, addressed exactly as the website addresses them
        |------------------------------------------------------------------
        | /{faculty}/{department}/{teacher} mirrors the public URL, so an app
        | handed a link somebody shared can ask for the same record.
        |
        | Declared last on purpose: three free segments would otherwise
        | swallow /faculties, /teachers and /publications above.
        */
        Route::prefix('{faculty}/{department}/{teacher}')->group(function () {
            Route::get('/', [TeacherProfileController::class, 'show'])->name('teachers.show');
            Route::get('publications', [TeacherProfileController::class, 'publications'])->name('teachers.publications');
            Route::get('publications/{publication}', [PublicationController::class, 'show'])->name('publications.show');

            /*
             * The CV and the vCard are the files the website already builds,
             * reached at an /api address. Pointed at the same controller rather
             * than reimplemented: two copies of a CV layout would drift, and the
             * version an app downloads should be the version the profile page
             * offers. Both honour the settings that can switch them off, and
             * resolve the teacher under the same visibility rule.
             */
            Route::get('cv', [TeacherController::class, 'cv'])->name('teachers.cv');
            Route::get('vcard', [TeacherController::class, 'vcard'])->name('teachers.vcard');
        });
    });
});
