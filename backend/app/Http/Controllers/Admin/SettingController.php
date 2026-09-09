<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SmtpConfigurationException;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Mail\StorefrontSmtpMailer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

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
                Setting::set($setting->key, $item['value']);
            }
        }

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cập nhật cài đặt thành công');
    }

    public function sendSmtpTest(Request $request, StorefrontSmtpMailer $mailer)
    {
        $validated = $request->validate([
            'recipient' => 'required|email:rfc|max:255',
        ]);

        try {
            $mailer->sendTest($validated['recipient']);
        } catch (SmtpConfigurationException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Không thể gửi email thử. Vui lòng kiểm tra lại máy chủ SMTP, cổng và thông tin đăng nhập.');
        }

        return back()->with('success', 'Đã gửi email thử. Hãy kiểm tra hộp thư nhận và thư mục Spam.');
    }
}
