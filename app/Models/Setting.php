<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Key/value site settings edited in Admin > Cài đặt website.
 * Read them with the global setting('key', $default) helper.
 */
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /** Keys whose value is a JSON list (repeatable rows in the admin form). */
    public const JSON_KEYS = ['contact_staff', 'home_fields', 'home_reasons'];

    protected $fillable = ['key', 'value'];

    protected static ?array $loaded = null;

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget(self::CACHE_KEY);
            self::$loaded = null;
        };
        static::saved($flush);
        static::deleted($flush);
    }

    /** Forget the per-request copy (new request/test, or after saving). */
    public static function flushLoaded(): void
    {
        self::$loaded = null;
    }

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        try {
            $values = Cache::rememberForever(self::CACHE_KEY, fn () => self::pluck('value', 'key')->all());
        } catch (Throwable) {
            // Table not migrated yet (fresh install, artisan commands): fall back to defaults.
            return [];
        }

        foreach (self::JSON_KEYS as $key) {
            if (isset($values[$key]) && is_string($values[$key])) {
                $values[$key] = json_decode($values[$key], true) ?: [];
            }
        }

        return self::$loaded = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::allValues()[$key] ?? null;

        return ($value === null || $value === '' || $value === []) ? $default : $value;
    }

    /** @param  array<string, mixed>  $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
            }
            self::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;

        if (! empty($values['site_name'])) {
            config(['app.name' => $values['site_name']]);
        }
    }
}
