<?php

use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\DepartmentController;
use App\Http\Controllers\Frontend\TeacherController;
use App\Http\Controllers\Frontend\PublicationController;
use Illuminate\Support\Facades\Route;

// Legacy teacher search for the admin "Create Teacher" page. Registered here
// rather than in routes/api.php because the browser calls it with the panel's
// session cookie, and the "api" middleware group is stateless. Must stay above
// the frontend catch-all routes below, which would otherwise swallow the path.
Route::middleware('auth')->group(function () {
    Route::get('/api/teacher/search', [TeacherApiController::class, 'search']);
});

// Public nested frontend routes (Blade + Livewire monolith, placed at the bottom)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/{faculty_short_name}', [HomeController::class, 'index'])->name('faculty.show');
Route::get('/{faculty_short_name}/{department_code}', [DepartmentController::class, 'show'])->name('department.show');
Route::get('/{faculty_short_name}/{department_code}/contact', [DepartmentController::class, 'contact'])->name('department.contact');
Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}', [TeacherController::class, 'show'])->name('teacher.show');
Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/vcard', [TeacherController::class, 'vcard'])->name('teacher.vcard');
Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/cv', [TeacherController::class, 'cv'])->name('teacher.cv');
Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/publication/{publication_slug}', [PublicationController::class, 'show'])->name('publication.show');
