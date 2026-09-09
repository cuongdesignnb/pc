<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * GET /api/v1/settings
     * Returns all public settings as key-value pairs.
     */
    public function index()
    {
        return response()->json(Setting::publicSettings())
            ->header('Cache-Control', 'public, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
