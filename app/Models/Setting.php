<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** قراءة إعداد مع كاش لكل الجدول */
    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('settings', fn () => static::pluck('value', 'key')->all());

        $value = $all[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }
}
