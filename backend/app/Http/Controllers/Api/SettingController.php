<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Checkout\PaymentMethodAvailability;

class SettingController extends Controller
{
    /**
     * GET /api/v1/settings
     * Returns all public settings as key-value pairs.
     */
    public function index(PaymentMethodAvailability $payments)
    {
        // Computed from the SePay configuration, so the footer shows bank transfer only when checkout offers it.
        $settings = Setting::publicSettings() + ['payment_sepay_enabled' => $payments->isAvailable('sepay')];

        return response()->json($settings)
            ->header('Cache-Control', 'public, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
