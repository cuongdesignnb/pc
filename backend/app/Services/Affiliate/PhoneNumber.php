<?php

namespace App\Services\Affiliate;

final class PhoneNumber
{
    /** "+84 912 345 678", "84912345678" and "0912.345.678" all become "0912345678". */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
