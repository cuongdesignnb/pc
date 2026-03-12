<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * Get all provinces (without wards for lighter response)
     */
    public function provinces()
    {
        $provinces = Cache::rememberForever('locations_provinces', function () {
            $json = json_decode(file_get_contents(base_path('locations.json')), true);

            return collect($json['provinces'])->map(function ($province) {
                return [
                    'name' => $province['name'],
                    'code' => $province['code'],
                    'type' => $province['type'],
                    'typename' => $province['typename'],
                    'fullname' => $province['fullname'],
                ];
            })->values()->all();
        });

        return response()->json($provinces);
    }

    /**
     * Get wards for a specific province by code
     */
    public function wards(string $provinceCode)
    {
        $wards = Cache::rememberForever("locations_wards_{$provinceCode}", function () use ($provinceCode) {
            $json = json_decode(file_get_contents(base_path('locations.json')), true);

            $province = collect($json['provinces'])->firstWhere('code', $provinceCode);

            if (!$province) {
                return null;
            }

            return $province['wards'] ?? [];
        });

        if ($wards === null) {
            abort(404, 'Province not found');
        }

        return response()->json($wards);
    }
}
