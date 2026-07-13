<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'frontend_driver' => Setting::get('frontend_driver', 'blade'),
            'nextjs_url'      => Setting::get('nextjs_url', ''),
            'active_theme'    => Setting::get('active_theme', 'theme_default'),
        ]);
    }
}
