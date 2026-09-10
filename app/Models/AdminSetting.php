<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class AdminSetting extends Model
{
    protected $table = 'admin_settings';

    protected $fillable = [
        'option_key',
        'option_value',
    ];

    public $timestamps = false;

    /**
     * Centralized whitelist of sensitive Admin Setting keys.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_KEYS = [
        'production_client_id',
        'production_client_secret',
        'amazon_refresh_token',
        'test_client_id',
        'test_client_secret',
        'test_refresh_token',
        'SHOPIFY_API_KEY',
        'SHOPIFY_API_SECRET',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'SMTP_username',
        'SMTP_password',
        'openai_api_key',
    ];

    /**
     * Determine if an option_key is in the sensitive keys whitelist.
     */
    public static function isSensitiveKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    /**
     * Determine if a string is already valid Laravel Crypt ciphertext.
     */
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Encrypt a sensitive value if not already encrypted.
     */
    public static function encryptValue(?string $key, ?string $value): ?string
    {
        if ($value === null || $value === '' || !static::isSensitiveKey($key)) {
            return $value;
        }

        if (static::isEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a sensitive value, with legacy plaintext fallback.
     */
    public static function decryptValue(?string $key, ?string $value): ?string
    {
        if ($value === null || $value === '' || !static::isSensitiveKey($key)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Graceful legacy plaintext fallback
            return $value;
        }
    }

    /**
     * Mutator for option_value: encrypts sensitive values before persistence.
     */
    public function setOptionValueAttribute($value): void
    {
        $key = $this->option_key ?? $this->attributes['option_key'] ?? null;
        $this->attributes['option_value'] = static::encryptValue($key, $value);
        if ($key) {
            static::forget($key);
        }
    }

    /**
     * Accessor for option_value: decrypts sensitive values on retrieval.
     */
    public function getOptionValueAttribute($value): ?string
    {
        $key = $this->option_key ?? $this->attributes['option_key'] ?? null;
        return static::decryptValue($key, $value);
    }

    /**
     * Boot the model and register encryption and cache invalidation events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $setting) {
            $key = $setting->option_key ?? $setting->attributes['option_key'] ?? null;
            $val = $setting->attributes['option_value'] ?? null;

            if ($key && $val !== null && $val !== '' && static::isSensitiveKey($key)) {
                if (!static::isEncrypted($val)) {
                    $setting->attributes['option_value'] = Crypt::encryptString($val);
                }
            }

            if ($key) {
                static::forget($key);
            }
        });

        static::saved(function (self $setting) {
            $key = $setting->option_key ?? $setting->attributes['option_key'] ?? null;
            if ($key) {
                static::forget($key);
            }
        });

        static::deleted(function (self $setting) {
            $key = $setting->option_key ?? $setting->attributes['option_key'] ?? null;
            if ($key) {
                static::forget($key);
            }
        });
    }

    /**
     * Retrieve a setting by key (with cache and automatic decryption).
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("admin_setting_{$key}", function () use ($key, $default) {
            try {
                $setting = static::where('option_key', $key)->first();

                if (! $setting || ! filled($setting->option_value)) {
                    return $default;
                }

                return $setting->option_value;
            } catch (\Throwable $e) {
                return $default;
            }
        });
    }

    /**
     * Invalidate cached setting value.
     */
    public static function forget(string $key): void
    {
        Cache::forget("admin_setting_{$key}");
        Cache::forget("setting_{$key}");
    }

    /**
     * Retrieve all settings with sensitive values decrypted.
     */
    public static function getAllDecrypted(): array
    {
        return static::all()->pluck('option_value', 'option_key')->toArray();
    }
}
