<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;

class AIConfigurationService
{
    private const CACHE_KEY = 'ai_configuration';

    private const CACHE_TTL = 3600; // 60 Minutes

    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {

            $settings = AdminSetting::whereIn('option_key', [
                'ai_provider',
                'openai_api_key',
                'openai_model',
                'openai_temperature',
                'openai_endpoint',
                'openai_max_tokens',
            ])->pluck('option_value', 'option_key');

            return [
                'provider' => $settings['ai_provider'] ?? 'openai',

                'api_key' => $settings['openai_api_key'] ?? '',

                'model' => $settings['openai_model'] ?? 'gpt-4.1-mini',

                'temperature' => (float) ($settings['openai_temperature'] ?? 0.1),

                'max_tokens' => (int) ($settings['openai_max_tokens'] ?? 1024),

                'endpoint' => $settings['openai_endpoint']
                    ?? 'https://api.openai.com/v1/chat/completions',
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
