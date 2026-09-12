<?php
namespace App\Casts;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class LegacyEncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') { return $value; }
        try { return Crypt::decryptString((string) $value); }
        catch (DecryptException) { return (string) $value; }
    }
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') { return $value; }
        try { Crypt::decryptString((string) $value); return (string) $value; }
        catch (DecryptException) { return Crypt::encryptString((string) $value); }
    }
}
