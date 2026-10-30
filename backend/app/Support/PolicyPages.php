<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * The policy pages Bộ Công Thương requires, and the {{tokens}} in their body
 * that are filled from the shop's settings when a page is served. The footer
 * menu lists published pages on its own (see Api\MenuController).
 */
final class PolicyPages
{
    /** Create the missing policy pages; pages the shop already wrote under the same slug are kept. */
    public static function install(): void
    {
        foreach (require database_path('data/policy_pages.php') as $page) {
            if (DB::table('pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }
            DB::table('pages')->insert($page + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Replace {{tokens}} with escaped setting values; unknown tokens are left as they are. */
    public static function fill(?string $text, bool $html = true): ?string
    {
        if ($text === null || ! str_contains($text, '{{')) {
            return $text;
        }
        $values = self::tokens();

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $match) use ($values, $html) {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }
            $value = $values[$match[1]] !== '' ? $values[$match[1]] : '(đang cập nhật)';

            return $html ? e($value) : $value;
        }, $text);
    }

    /** @return array<string, string> */
    private static function tokens(): array
    {
        $get = fn (string $key, string $fallback = ''): string => trim((string) (Setting::get($key) ?: $fallback));
        $website = $get('storefront_product_contact_footer_website_url', (string) config('app.frontend_url', ''));

        return [
            'ten_doanh_nghiep' => $get('legal_business_name', $get('site_name')),
            'ten_website' => $get('site_name'),
            'website' => preg_replace('#^https?://#', '', rtrim($website, '/')) ?? $website,
            'dia_chi' => $get('legal_head_office_address', $get('contact_address')),
            'hotline' => $get('contact_hotline', $get('contact_phone')),
            'email' => $get('legal_email', $get('contact_email')),
            'gio_lam_viec' => $get('business_hours'),
        ];
    }
}
