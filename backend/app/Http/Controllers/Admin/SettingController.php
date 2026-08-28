<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('group')->orderBy('id')->get()->map(function (Setting $setting) {
            if ($setting->type === 'password' || str_ends_with($setting->key, '_api_key')) {
                $setting->type = 'password';
            }
            if ($setting->type === 'password' && filled($setting->value)) {
                $setting->value = '********';
            }

            return $setting;
        })->groupBy('group');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->input('settings') as $item) {
            $setting = Setting::where('key', $item['key'])->first();
            if ($setting && ! (($setting->type === 'password' || str_ends_with($setting->key, '_api_key')) && in_array($item['value'], ['', '********'], true))) {
                $value = $item['value'];
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                if (Setting::isAssetKey($setting->key) && is_string($value)) {
                    $value = PublicAssetUrl::normalize($value);
                }
                $setting->update(['value' => $value]);
            }
        }

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cập nhật cài đặt thành công');
    }
}
