<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\AmazonSchemaFilterService;
use App\Services\AI\AIPromptBuilderService;
use App\Services\AI\AIListingService;
use App\Services\AI\AIResponseValidator;
use Throwable;

readonly class AIAutoFillService
{
    public function __construct(
        private AmazonSchemaFilterService $filterService,
        private AIPromptBuilderService $promptService,
        private AIListingService $listingService,
        private AIResponseValidator $validatorService
    ) {}

    /**
     * Executes the complete end-to-end AI Auto-Fill pipeline.
     *
     * @param array $shopifyProduct The normalized Shopify product data.
     * @param array $resolvedSchema The fully resolved Amazon PTD schema.
     * @param array $currentValues  Fields already populated by the user.
     * @param string $category      The target Amazon product category.
     * @return array{success: bool, data: array, errors: array, warnings: array, trace_id?: string, usage?: array|null, classification?: array}
     */
    public function generate(
        array $shopifyProduct,
        array $resolvedSchema,
        array $currentValues = [],
        string $category = ''
    ): array {
        $traceId   = (string) Str::uuid();
        $startTime = microtime(true);

        Log::info("AI Auto-Fill Pipeline Started [{$traceId}]", [
            'category' => $category,
        ]);

        try {
            // 1. Classify Schema
            $classification = $this->filterService->classify($resolvedSchema);

            // 2. Build AI Prompts
            $prompts = $this->promptService->buildPrompt(
                $shopifyProduct,
                $classification,
                $currentValues,
                $category
            );

            // 3. Generate AI Response
            $aiResponse = $this->listingService->generate(
                $prompts['system_prompt'],
                $prompts['user_prompt']
            );

            if (!$aiResponse['success']) {
                return $this->handleError(
                    $traceId,
                    $aiResponse['error'] ?? 'Unknown API Error',
                    $startTime,
                    ['classification' => $classification]
                );
            }

            // 4. Validate AI Response
            $aiData    = $aiResponse['content'] ?? [];
            $validated = $this->validatorService->validate($aiData, $classification);

            if (!$validated['success']) {
                // Pass original structured error array directly
                return $this->handleError(
                    $traceId,
                    $validated['errors'],
                    $startTime,
                    [
                        'classification' => $classification,
                        'usage'          => $aiResponse['usage'] ?? null,
                    ]
                );
            }

            // 5. Return Validated Payload with Metadata
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info("AI Auto-Fill Pipeline Completed [{$traceId}]", [
                'duration_ms' => $duration,
                'warnings'    => count($validated['warnings']),
            ]);

            return [
                'success'        => true,
                'data'           => $validated['data'],
                'errors'         => [],
                'warnings'       => $validated['warnings'],
                'trace_id'       => $traceId,
                'usage'          => $aiResponse['usage'] ?? null,
                'classification' => $classification,
            ];
        } catch (Throwable $e) {
            return $this->handleError(
                $traceId,
                "Pipeline Exception: {$e->getMessage()}",
                $startTime
            );
        }
    }

    public function generateSingleField(
        string $productName,
        string $category,
        string $field,
        ?string $fieldDescription = null,
        ?string $fieldHint = null
    ): array {

        $prompts = $this->promptService->buildSingleFieldPrompt(
            productName: $productName,
            category: $category,
            field: $field,
            fieldDescription: $fieldDescription,
            fieldHint: $fieldHint,
        );

        $response = $this->listingService->generate(
            $prompts['system_prompt'],
            $prompts['user_prompt']
        );

        if (! $response['success']) {

            return [
                'success' => false,
                'data'    => null,
                'message' => $response['error'] ?? 'AI generation failed.',
            ];
        }

        $content = $response['content'];

        // AI may return plain text or a single-key JSON object
        if (is_array($content)) {

            if (array_key_exists($field, $content)) {
                $value = $content[$field];
            } else {
                $value = reset($content);
            }
        } else {

            $value = $content;
        }

        return [
            'success' => true,
            'data'    => $value,
            'usage'   => $response['usage'] ?? null,
        ];
    }

    public function generateGenericListing(
        string $productName,
        ?string $productDescription,
        string $category
    ): array {

        $prompts = $this->promptService->buildGenericPrompt(
            productName: $productName,
            productDescription: $productDescription,
            category: $category
        );

        $response = $this->listingService->generate(
            $prompts['system_prompt'],
            $prompts['user_prompt']
        );


        if (!$response['success']) {
            return [
                'success' => false,
                'data'    => [],
                'errors'  => [
                    $response['error'] ?? 'AI generation failed.'
                ],
                'usage'   => null,
            ];
        }

        return [
            'success' => true,
            'data'    => $response['content'] ?? [],
            'errors'  => [],
            'usage'   => $response['usage'] ?? null,
        ];
    }


    public function generateErrorAutoFill(
        string $productName,
        ?string $productDescription,
        string $category,
        array $errors
    ): array {
        $prompts = $this->promptService->buildErrorAutoFillPrompt(
            productName: $productName,
            productDescription: $productDescription,
            category: $category,
            errors: $errors
        );

        $response = $this->listingService->generate(
            $prompts['system_prompt'],
            $prompts['user_prompt']
        );

        if (!$response['success']) {
            return [
                'success' => false,
                'data' => [],
                'errors' => [
                    $response['error'] ?? 'AI generation failed.'
                ],
                'usage' => null,
            ];
        }

        return [
            'success' => true,
            'data' => $response['content'] ?? [],
            'errors' => [],
            'usage' => $response['usage'] ?? null,
        ];
    }
    /**
     * Halts the pipeline safely and returns a standardized, metadata-enriched error response.
     */
    private function handleError(string $traceId, array|string $errors, float $startTime, array $metadata = []): array
    {
        $duration   = round((microtime(true) - $startTime) * 1000, 2);
        $errorArray = is_array($errors) ? $errors : [$errors];

        Log::error("AI Auto-Fill Pipeline Failed [{$traceId}]", [
            'errors'      => $errorArray,
            'duration_ms' => $duration,
        ]);

        return array_merge([
            'success'  => false,
            'data'     => [],
            'errors'   => $errorArray,
            'warnings' => [],
            'trace_id' => $traceId,
        ], $metadata);
    }
}
