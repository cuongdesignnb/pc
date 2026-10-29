<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SmtpConfigurationException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Mail\StorefrontSmtpMailer;
use App\Support\GoogleMapsEmbed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class SettingController extends Controller
{
    // Kept in the database but no longer read: AI article text now uses the
    // shared content provider (openai_*), images use openai_image_*.
    private const RETIRED_KEYS = ['chatgpt_api_key', 'chatgpt_model'];

    public function index()
    {
        $settings = Setting::whereNotIn('key', self::RETIRED_KEYS)->orderBy('group')->orderBy('id')->get()->map(function (Setting $setting) {
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
            // Choices for the homepage product tabs.
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'parent_id']),
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
            if ($setting && in_array($setting->key, [
                'storefront_product_contact_footer_website_url',
                'storefront_product_contact_footer_maps_url',
            ], true) && filled($item['value'] ?? null)) {
                Validator::make(['value' => trim((string) $item['value'])], [
                    'value' => ['url', 'starts_with:https://'],
                ], [
                    'value.url' => 'Website và Maps phải là URL hợp lệ.',
                    'value.starts_with' => 'Website và Maps phải dùng HTTPS.',
                ])->validate();
            }
            if ($setting && $setting->key === 'contact_map_embed_url' && filled($item['value'] ?? null)) {
                $item['value'] = GoogleMapsEmbed::normalize((string) $item['value']);
                if ($item['value'] === null) {
                    throw ValidationException::withMessages([
                        'contact_map_embed_url' => 'Bản đồ phải là mã nhúng hoặc link "Nhúng bản đồ" của Google Maps (https://www.google.com/maps/embed?...).',
                    ]);
                }
            }
            if ($setting && $setting->type === 'color' && filled($item['value'] ?? null)) {
                $item['value'] = strtoupper(trim((string) $item['value']));
                if (! preg_match('/^#[0-9A-F]{6}$/', $item['value'])) {
                    throw ValidationException::withMessages([$setting->key => "{$setting->label}: mã màu phải có dạng #RRGGBB."]);
                }
            }
            if ($setting && $setting->key === 'homepage_product_tabs') {
                $item['value'] = $this->productTabs($item['value'] ?? null);
            }
            if ($setting && ! (($setting->type === 'password' || str_ends_with($setting->key, '_api_key')) && in_array($item['value'], ['', '********'], true))) {
                Setting::set($setting->key, $item['value']);
            }
        }

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cập nhật cài đặt thành công');
    }

    /** Normalise the homepage product tabs to a JSON list of {label, category_slug}. */
    private function productTabs(mixed $value): string
    {
        $tabs = is_string($value) ? json_decode($value, true) : $value;
        if ($value !== null && $value !== '' && ! is_array($tabs)) {
            throw ValidationException::withMessages(['homepage_product_tabs' => 'Danh sách tab sản phẩm không hợp lệ.']);
        }

        $tabs = collect(is_array($tabs) ? $tabs : [])
            ->filter(fn ($tab): bool => is_array($tab) && filled($tab['category_slug'] ?? null))
            ->map(fn (array $tab): array => [
                'label' => mb_substr(trim((string) ($tab['label'] ?? '')), 0, 60),
                'category_slug' => trim((string) $tab['category_slug']),
            ])
            ->values();
        if ($tabs->count() > 6) {
            throw ValidationException::withMessages(['homepage_product_tabs' => 'Khối sản phẩm có tối đa 6 tab.']);
        }
        $unknown = $tabs->pluck('category_slug')->diff(Category::whereIn('slug', $tabs->pluck('category_slug'))->pluck('slug'));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['homepage_product_tabs' => 'Danh mục không tồn tại: '.$unknown->implode(', ')]);
        }

        return $tabs->toJson(JSON_UNESCAPED_UNICODE);
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
