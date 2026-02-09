<?php

use App\Http\Controllers\Api\TeacherApiController;
use Illuminate\Support\Facades\Route;

Route::get('/teacher/search', [TeacherApiController::class, 'search']);
