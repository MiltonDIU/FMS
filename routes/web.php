<?php

use App\Http\Controllers\Admin\IntegrationMappingController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\DepartmentController;
use App\Http\Controllers\Frontend\TeacherController;
use App\Http\Controllers\Frontend\PublicationController;
use App\Http\Middleware\HandleFrontendDriverMiddleware;
use Illuminate\Support\Facades\Route;

// Integration Mapping API endpoints (placed first)
Route::prefix('admin/integration-mappings')->group(function () {
    Route::post('/fetch-api', [IntegrationMappingController::class, 'fetchApiData']);
    Route::post('/model-fields', [IntegrationMappingController::class, 'getModelFields']);
});

// Profile & Import API direct endpoints
Route::get('/profile/{webpage}', [\App\Http\Controllers\Api\V1\FrontendApiController::class, 'profileByWebpage']);
Route::get('/teachers/preview', [\App\Http\Controllers\Api\V1\FrontendApiController::class, 'previewTeacherImport']);
Route::post('/teachers/import', [\App\Http\Controllers\Api\V1\FrontendApiController::class, 'confirmTeacherImport']);

// Public nested frontend routes protected by Frontend Driver Middleware (placed at the bottom)
Route::middleware(HandleFrontendDriverMiddleware::class)->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/{faculty_short_name}', [HomeController::class, 'index'])->name('faculty.show');
    Route::get('/{faculty_short_name}/{department_code}', [DepartmentController::class, 'show'])->name('department.show');
    Route::get('/{faculty_short_name}/{department_code}/contact', [DepartmentController::class, 'contact'])->name('department.contact');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}', [TeacherController::class, 'show'])->name('teacher.show');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/vcard', [TeacherController::class, 'vcard'])->name('teacher.vcard');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/cv', [TeacherController::class, 'cv'])->name('teacher.cv');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/publication/{publication_slug}', [PublicationController::class, 'show'])->name('publication.show');
});
