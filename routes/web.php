<?php

use App\Http\Controllers\Admin\IntegrationMappingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Integration Mapping API endpoints
Route::prefix('admin/integration-mappings')->group(function () {
    Route::post('/fetch-api', [IntegrationMappingController::class, 'fetchApiData']);
    Route::post('/model-fields', [IntegrationMappingController::class, 'getModelFields']);
});
