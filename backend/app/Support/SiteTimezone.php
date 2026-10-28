<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The shop's local time zone, chosen in Cài đặt → Chung. The database keeps
 * UTC (config app.timezone); admin date-time inputs are read and shown in
 * this zone. Anything unknown falls back to Vietnam time.
 */
final class SiteTimezone
{
    public const DEFAULT = 'Asia/Ho_Chi_Minh';

    public static function get(): string
    {
        $value = trim((string) Setting::get('site_timezone', self::DEFAULT));

        return in_array($value, timezone_identifiers_list(), true) ? $value : self::DEFAULT;
    }
}
