<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** A 301/302 rule the storefront applies before rendering (SEO → Chuyển hướng). */
class Redirect extends Model
{
    public const CACHE_KEY = 'redirects.public';

    protected $fillable = ['source_path', 'target', 'status_code', 'is_active', 'note'];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** "/Gioi-Thieu/?a=1" → "/gioi-thieu"; null when it is not a local path. */
    public static function normalizePath(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $value)) {
            $value = (string) (parse_url($value, PHP_URL_PATH) ?: '/');
        }
        $value = (string) preg_replace('/[?#].*$/', '', $value);
        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }
        $value = rtrim(mb_strtolower(rawurldecode($value)), '/');

        return $value === '' ? '/' : $value;
    }
}
