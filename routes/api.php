<?php

use App\Http\Controllers\Api\TeacherApiController;
use Illuminate\Support\Facades\Route;

Route::get('/teachers/search', [TeacherApiController::class, 'search']);
