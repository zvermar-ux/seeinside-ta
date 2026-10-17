<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** System-wide settings (key → JSON value), cached until the next save */
class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::flushCache();
    }

    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();
        static::flushCache();
    }

    public static function flushCache(): void
    {
        Cache::forget('app_settings');
        static::$memo = null;
    }

    protected static ?array $memo = null;

    public static function forgetMemo(): void
    {
        static::$memo = null;
    }

    protected static function allCached(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        try {
            $cached = Cache::get('app_settings');
            if (is_array($cached)) {
                return static::$memo = $cached;
            }
            if (! Schema::hasTable('settings')) {
                return static::$memo = [];   // not migrated yet – don't cache
            }
            $all = static::query()->pluck('value', 'key')->all();
            Cache::forever('app_settings', $all);

            return static::$memo = $all;
        } catch (Throwable) {
            return static::$memo = [];   // database not ready yet (install / migrate)
        }
    }
}
