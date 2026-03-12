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
        return response()->json(Setting::publicSettings());
    }
}
