<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AdminSetting extends Model
{
    protected $table = 'admin_settings';

    protected $fillable = [
        'option_key',
        'option_value',
    ];

    public $timestamps = false;

    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("admin_setting_{$key}", function () use ($key, $default) {

            $value = static::where('option_key', $key)
                ->value('option_value');

            return filled($value) ? $value : $default;
        });
    }

    public static function forget(string $key): void
    {
        Cache::forget("admin_setting_{$key}");
    }
}
