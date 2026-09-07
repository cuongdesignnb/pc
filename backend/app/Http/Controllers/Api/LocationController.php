<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Locations\LocationDirectory;

class LocationController extends Controller
{
    public function __construct(private readonly LocationDirectory $locations) {}

    /**
     * Get all provinces (without wards for lighter response)
     */
    public function provinces()
    {
        return response()->json($this->locations->provinces());
    }

    /**
     * Get wards for a specific province by code
     */
    public function wards(string $provinceCode)
    {
        $wards = $this->locations->wards($provinceCode);

        if ($wards === null) {
            abort(404, 'Province not found');
        }

        return response()->json($wards);
    }
}
