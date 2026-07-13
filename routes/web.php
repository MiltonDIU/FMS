<?php

use App\Http\Controllers\Admin\IntegrationMappingController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\TeacherController;
use App\Http\Middleware\HandleFrontendDriverMiddleware;
use Illuminate\Support\Facades\Route;

// Public frontend routes protected by Frontend Driver Middleware
Route::middleware(HandleFrontendDriverMiddleware::class)->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/teachers/{id}', [TeacherController::class, 'show']);
});

// Integration Mapping API endpoints
Route::prefix('admin/integration-mappings')->group(function () {
    Route::post('/fetch-api', [IntegrationMappingController::class, 'fetchApiData']);
    Route::post('/model-fields', [IntegrationMappingController::class, 'getModelFields']);
});
