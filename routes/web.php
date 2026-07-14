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

// Public nested frontend routes protected by Frontend Driver Middleware (placed at the bottom)
Route::middleware(HandleFrontendDriverMiddleware::class)->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/{faculty_short_name}', [HomeController::class, 'index'])->name('faculty.show');
    Route::get('/{faculty_short_name}/{department_code}', [DepartmentController::class, 'show'])->name('department.show');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}', [TeacherController::class, 'show'])->name('teacher.show');
    Route::get('/{faculty_short_name}/{department_code}/{teacher_webpage}/publication/{publication_slug}', [PublicationController::class, 'show'])->name('publication.show');
});
