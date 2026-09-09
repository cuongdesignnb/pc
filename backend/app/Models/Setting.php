<?php

namespace App\Models;

use App\Support\PublicAssetUrl;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'label',
        'options',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (! $setting) {
            return $default;
        }

        $value = self::isSecret($setting)
            ? self::decryptSecret($setting->value)
            : $setting->value;

        return self::castValue($value, $setting->type);
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        if (static::isAssetKey($key) && is_string($value)) {
            $value = PublicAssetUrl::normalize($value);
        }

        $setting = static::where('key', $key)->first();

        if ($setting) {
            $storedValue = self::serializeValue($value);
            if (self::isSecret($setting)) {
                $storedValue = self::encryptSecret($storedValue);
            }

            $setting->update(['value' => $storedValue]);
        } else {
            static::create([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'group' => 'general',
                'type' => 'text',
                'label' => $key,
            ]);
        }

        Cache::forget("setting.{$key}");
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }

    /**
     * Get all settings grouped.
     */
    public static function allGrouped(): array
    {
        return Cache::remember('settings.all', 3600, function () {
            return static::orderBy('group')->orderBy('id')
                ->get()
                ->groupBy('group')
                ->toArray();
        });
    }

    /**
     * Get all public settings as key-value pairs.
     */
    public static function publicSettings(): array
    {
        return Cache::remember('settings.public', 3600, function () {
            return static::where('is_public', true)
                ->get()
                ->mapWithKeys(function ($setting) {
                    $value = self::castValue($setting->value, $setting->type);
                    if (self::isAssetKey($setting->key) && is_string($value)) {
                        $value = PublicAssetUrl::normalize($value);
                    }

                    return [$setting->key => $value];
                })
                ->toArray();
        });
    }

    public static function isAssetKey(string $key): bool
    {
        return in_array($key, [
            'site_logo',
            'site_logo_white',
            'site_favicon',
            'seo_og_image',
        ], true);
    }

    public static function isSecret(self $setting): bool
    {
        return $setting->type === 'password' || str_ends_with($setting->key, '_api_key');
    }

    private static function serializeValue(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }

    private static function encryptSecret(string $value): string
    {
        return $value === '' ? '' : Crypt::encryptString($value);
    }

    private static function decryptSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Existing secrets may predate encryption. Keep them usable and
            // encrypt them next time the administrator saves the setting.
            return $value;
        }
    }

    /**
     * Cast value based on type.
     */
    private static function castValue(?string $value, ?string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : 0,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Clear all setting caches.
     */
    public static function clearCache(): void
    {
        $keys = static::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting.{$key}");
        }
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }
}
