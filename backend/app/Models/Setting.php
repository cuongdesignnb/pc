<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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

        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => is_array($value) ? json_encode($value) : (string) $value]);
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
                    return [$setting->key => self::castValue($setting->value, $setting->type)];
                })
                ->toArray();
        });
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
