<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AIErrorHandler
{
    private const NOTIFICATION_KEY = 'ai_error';

    /**
     * Handle an AI-related exception.
     *
     * @param  Throwable $exception
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function handle(Throwable $exception, array $context = []): array
    {
        $traceId = $context['trace_id'] ?? uniqid('ai_', true);

        $error = $this->classify($exception, $context);

        $category = $error['category'];
        $title = $error['title'];
        $message = $error['message'];

        /*
         * Technical information goes ONLY into logs.
         * Never expose credentials or raw provider responses to admins/users.
         */
        Log::error('AI Error', [
            'trace_id' => $traceId,
            'category' => $category,
            'title' => $title,
            'exception' => get_class($exception),
            'technical_message' => $exception->getMessage(),
            'provider' => $context['provider'] ?? null,
            'model' => $context['model'] ?? null,
            'feature' => $context['feature'] ?? null,
            'shop_id' => $context['shop_id'] ?? null,
            'http_status' => $context['http_status'] ?? null,
            'provider_error' => $this->sanitizeProviderError(
                $context['provider_error'] ?? null
            ),
        ]);

        /*
         * Admin-facing notification contains only
         * human-readable information.
         */
        $this->createAdminNotification(
            $category,
            $title,
            $message
        );

        return [
            'success' => false,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'trace_id' => $traceId,
        ];
    }

    /**
     * Classify an AI exception and generate a human-readable message.
     *
     * @param  Throwable $exception
     * @param  array<string, mixed> $context
     * @return array{category: string, title: string, message: string}
     */
    private function classify(Throwable $exception, array $context): array
    {
        $status = $context['http_status'] ?? null;

        $providerError = strtolower(
            (string) ($context['provider_error'] ?? '')
        );

        $exceptionMessage = strtolower($exception->getMessage());

        $combinedError = $providerError . ' ' . $exceptionMessage;

        /*
         * Configuration errors
         */
        if (
            str_contains($combinedError, 'api key') ||
            str_contains($combinedError, 'api_key') ||
            str_contains($combinedError, 'endpoint') ||
            str_contains($combinedError, 'configuration') ||
            str_contains($combinedError, 'not configured')
        ) {
            return [
                'category' => 'configuration',
                'title' => 'AI Configuration Error',
                'message' => 'The AI service is not configured correctly. Please check the AI provider, API key, model, and endpoint configuration.',
            ];
        }

        /*
         * Authentication / authorization
         */
        if (
            $status === 401 ||
            $status === 403 ||
            str_contains($combinedError, 'unauthorized') ||
            str_contains($combinedError, 'authentication') ||
            str_contains($combinedError, 'invalid api key') ||
            str_contains($combinedError, 'invalid_api_key') ||
            str_contains($combinedError, 'permission denied')
        ) {
            return [
                'category' => 'authentication',
                'title' => 'AI Authentication Error',
                'message' => 'The AI provider rejected the configured credentials. Please verify the API key and account permissions.',
            ];
        }

        /*
         * Quota / billing / usage limit
         *
         * Keep this before generic rate-limit handling because
         * providers commonly return quota errors using HTTP 429.
         */
        if (
            str_contains($combinedError, 'quota') ||
            str_contains($combinedError, 'insufficient_quota') ||
            str_contains($combinedError, 'billing') ||
            str_contains($combinedError, 'usage limit') ||
            str_contains($combinedError, 'exceeded your current quota')
        ) {
            return [
                'category' => 'quota_exceeded',
                'title' => 'AI Usage Limit Reached',
                'message' => 'The AI provider usage limit has been reached. Please check the AI account usage or billing settings.',
            ];
        }

        /*
         * Rate limiting
         */
        if (
            $status === 429 ||
            str_contains($combinedError, 'rate limit') ||
            str_contains($combinedError, 'rate_limit') ||
            str_contains($combinedError, 'too many requests')
        ) {
            return [
                'category' => 'rate_limit',
                'title' => 'AI Rate Limit Reached',
                'message' => 'The AI provider is temporarily rate limiting requests. Please wait and try again later.',
            ];
        }

        /*
         * Timeout / network problems
         */
        if (
            $this->isTimeoutException($exception) ||
            str_contains($combinedError, 'timeout') ||
            str_contains($combinedError, 'timed out') ||
            str_contains($combinedError, 'connection refused') ||
            str_contains($combinedError, 'connection failed') ||
            str_contains($combinedError, 'could not resolve host')
        ) {
            return [
                'category' => 'timeout',
                'title' => 'AI Connection Error',
                'message' => 'The application could not connect to the AI provider in time. Please try again later.',
            ];
        }

        /*
         * Invalid request / bad payload
         */
        if (
            $status === 400 ||
            $status === 422 ||
            str_contains($combinedError, 'invalid request') ||
            str_contains($combinedError, 'invalid_request') ||
            str_contains($combinedError, 'invalid parameter') ||
            str_contains($combinedError, 'bad request')
        ) {
            return [
                'category' => 'invalid_request',
                'title' => 'AI Request Error',
                'message' => 'The AI provider rejected the request. Please check the AI request configuration and input data.',
            ];
        }

        /*
         * JSON / response parsing
         */
        if (
            $exception instanceof \JsonException ||
            str_contains($combinedError, 'json') ||
            str_contains($combinedError, 'malformed json') ||
            str_contains($combinedError, 'invalid json')
        ) {
            return [
                'category' => 'json_parsing',
                'title' => 'AI Response Error',
                'message' => 'The AI provider returned a response that could not be processed. Please try again.',
            ];
        }

        /*
         * Provider-side server errors
         */
        if (
            is_int($status) &&
            $status >= 500 &&
            $status <= 599
        ) {
            return [
                'category' => 'provider_error',
                'title' => 'AI Provider Error',
                'message' => 'The AI provider is currently experiencing a server-side problem. Please try again later.',
            ];
        }

        /*
         * Unknown / fallback
         */
        return [
            'category' => 'unknown',
            'title' => 'AI Service Error',
            'message' => 'An unexpected AI service error occurred. Please try again later.',
        ];
    }

    /**
     * Detect timeout/network exceptions without depending
     * on one specific HTTP client implementation.
     */
    private function isTimeoutException(Throwable $exception): bool
    {
        $class = strtolower(get_class($exception));
        $message = strtolower($exception->getMessage());

        return (
            str_contains($class, 'connectionexception') ||
            str_contains($class, 'connectexception') ||
            str_contains($class, 'timeoutexception') ||
            str_contains($message, 'timeout') ||
            str_contains($message, 'timed out')
        );
    }

    /**
     * Create the admin in-app notification.
     */
    private function createAdminNotification(
        string $category,
        string $title,
        string $message
    ): void {
        try {
            $cacheKey = 'ai_error_notification_throttle_' . $category;

            if (Cache::has($cacheKey)) {
                return;
            }

            NotificationService::send(
                self::NOTIFICATION_KEY,
                $title,
                $message
            );

            Cache::put(
                $cacheKey,
                true,
                now()->addHours(2)
            );
        } catch (Throwable $exception) {
            Log::error('Failed to send AI admin notification', [
                'exception' => get_class($exception),
                'technical_message' => $exception->getMessage(),
            ]);
        }
    }
    /**
     * Prevent huge or sensitive provider responses from entering logs.
     *
     * @param mixed $providerError
     */
    private function sanitizeProviderError(mixed $providerError): mixed
    {
        if ($providerError === null) {
            return null;
        }

        if (is_array($providerError)) {
            return array_slice($providerError, 0, 20, true);
        }

        if (is_string($providerError)) {
            return mb_substr($providerError, 0, 4000);
        }

        return get_debug_type($providerError);
    }
}
