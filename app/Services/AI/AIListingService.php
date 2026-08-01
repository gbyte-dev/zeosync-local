<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use App\Services\AIConfigurationService;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Throwable;

readonly class AIListingService
{
    public function __construct(
        private AIConfigurationService $configService
    ) {}


    /**
     * Sends the generated prompts to the AI provider and retrieves the structured JSON.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array{success: bool, content: ?array, raw: ?string, usage: ?array, error: ?string}
     */
    public function generate(string $systemPrompt, string $userPrompt): array
    {
        $startTime = microtime(true);
        $config = $this->configService->get();
        $rawText = null;
        $jsonText = null;

        try {
            $payload = $this->buildPayload($systemPrompt, $userPrompt, $config);
            $response = $this->sendRequest($payload, $config);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $responseArray = $response->json();
            $rawText = $this->extractMessage($responseArray);
            // $usage   = $responseArray['usage'] ?? [];

            $usage = [
                'prompt_tokens' => $responseArray['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $responseArray['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $responseArray['usageMetadata']['totalTokenCount'] ?? 0,
            ];

            // this is token counter for test only start
            $stats = Cache::get('ai_test_tokens', [
                'prompt' => 0,
                'completion' => 0,
                'total' => 0,
            ]);

            $stats['prompt'] += $usage['prompt_tokens'] ?? 0;
            $stats['completion'] += $usage['completion_tokens'] ?? 0;
            $stats['total'] += $usage['total_tokens'] ?? 0;

            Cache::forever('ai_test_tokens', $stats);
            $jsonText = $this->extractJson($rawText);

            Log::info('AI Extracted JSON', [
                'json' => $jsonText,
            ]);

            $content = json_decode($jsonText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {

                Log::error('AI Invalid JSON', [
                    'json'  => $jsonText,
                    'error' => json_last_error_msg(),
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'raw'     => $rawText,
                    'usage'   => $usage,
                    'error'   => '[JSON Parsing Failure]: ' . json_last_error_msg(),
                ];
            }

            Log::info('AI Listing Generation Successful', [
                'duration_ms' => $duration,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ]);

            return [
                'success' => true,
                'content' => $content,
                'raw'     => $rawText,
                'usage'   => $usage,
                'error'   => null,
            ];
        } catch (Throwable $e) {

            Log::error('AI Listing Generation Failed', [
                'message'   => $e->getMessage(),
                'raw_text'  => $rawText,
                'json_text' => $jsonText,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return $this->handleFailure($e, $duration);
        }
    }


    /**
     * Constructs the specific payload structure required by the OpenAI API.
     */
    // private function buildPayload(string $systemPrompt, string $userPrompt, array $config): array
    // {
    //     return [
    //         'model' => $config['model'],
    //         'messages' => [
    //             [
    //                 'role' => 'system',
    //                 'content' => $systemPrompt
    //             ],
    //             [
    //                 'role' => 'user',
    //                 'content' => $userPrompt
    //             ]
    //         ],
    //         'temperature' => (float) $config['temperature'],
    //         'response_format' => ['type' => 'json_object'],
    //     ];
    // }
    private function buildPayload(string $systemPrompt, string $userPrompt, array $config): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $systemPrompt . "\n\n" . $userPrompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float) $config['temperature'],
            ]
        ];
    }


    /**
     * Executes the HTTP request with strict timeouts and retry resilience.
     * 
     * @throws RequestException|ConnectionException
     */
    // private function sendRequest(array $payload, array $config): \Illuminate\Http\Client\Response
    // {
    //     $apiKey = $config['api_key'] ?? null;
    //     $endpoint = $config['endpoint'] ?? null;

    //     if (empty($apiKey)) {
    //         throw new \RuntimeException('AI API key is missing from configuration.');
    //     }

    //     if (empty($endpoint)) {
    //         throw new \RuntimeException('AI endpoint is missing from configuration.');
    //     }

    //     return Http::acceptJson()
    //         ->asJson()
    //         ->timeout(60)
    //         ->retry(2, 500)
    //         ->withToken($apiKey)
    //         ->post($endpoint, $payload)
    //         ->throw();
    // }

    private function sendRequest(array $payload, array $config): \Illuminate\Http\Client\Response
    {
        $apiKey = $config['api_key'] ?? null;
        $endpoint = str_replace(
            '{model}',
            $config['model'],
            $config['endpoint']
        );

        if (empty($apiKey)) {
            throw new \RuntimeException('AI API key is missing.');
        }

        if (empty($endpoint)) {
            throw new \RuntimeException('AI endpoint is missing.');
        }

        if (($config['provider'] ?? 'openai') === 'gemini') {

            return Http::acceptJson()
                ->asJson()
                ->timeout(60)
                ->retry(2, 500)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($endpoint, $payload)
                ->throw();
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(60)
            ->retry(2, 500)
            ->withToken($apiKey)
            ->post($endpoint, $payload)
            ->throw();
    }

    /**
     * Isolates the generated text content from the provider's response envelope.
     */
    // private function extractMessage(array $response): string
    // {
    //     return $response['choices'][0]['message']['content'] ?? '';
    // }

    private function extractMessage(array $response): string
    {
        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Robustly strips conversational filler and markdown blocks to expose pure JSON.
     */
    private function extractJson(string $rawText): string
    {
        $text = trim($rawText);

        // Target standard Markdown JSON blocks
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: Locate the outermost JSON object boundaries
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start !== false && $end !== false) {
            return substr($text, $start, $end - $start + 1);
        }

        // If no boundaries found, return as-is to allow decodeJson to throw a clean error
        return $text;
    }

    /**
     * Decodes the string, enforcing strict error throwing on invalid formats.
     * 
     * @throws JsonException
     */
    private function decodeJson(string $jsonString): array
    {
        return json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Intercepts failures, logs critical non-sensitive metadata, and normalizes the return array.
     */
    private function handleFailure(Throwable $e, float $duration): array
    {
        $errorType = 'General API Error';

        if ($e instanceof ConnectionException) {
            $errorType = 'Network Timeout/Connection Failure';
        } elseif ($e instanceof RequestException) {
            $errorType = 'HTTP Request Error (e.g. 4xx/5xx)';
        } elseif ($e instanceof JsonException) {
            $errorType = 'JSON Parsing Failure';
        }

        Log::error('AI Listing Generation Failed', [
            'type'        => $errorType,
            'message'     => $e->getMessage(),
            'duration_ms' => $duration,
            'exception'   => get_class($e),
        ]);

        return [
            'success' => false,
            'content' => null,
            'raw'     => null,
            'usage'   => null,
            'error'   => "[{$errorType}]: " . $e->getMessage(),
        ];
    }
}
