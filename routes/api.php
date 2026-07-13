<?php

use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\Api\PublicDataApiController;
use App\Http\Controllers\Api\PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/teacher/search', [TeacherApiController::class, 'search']);

Route::get('/v1/settings', [PublicSettingsController::class, 'index']);
Route::get('/v1/faculties', [PublicDataApiController::class, 'faculties']);
Route::get('/v1/departments', [PublicDataApiController::class, 'departments']);
Route::get('/v1/teachers', [PublicDataApiController::class, 'teachers']);
Route::get('/v1/teachers/{webpage}', [PublicDataApiController::class, 'teacherDetails']);
