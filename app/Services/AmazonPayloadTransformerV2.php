<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AmazonTransformerConfig;
use App\Services\Transformers\TransformerResolver;
use App\Services\Validators\SchemaValidator;
use Illuminate\Support\Facades\Log;
use App\Services\Transformers\SchemaWalker;
use RuntimeException;

/**
 * Orchestrates the validation and transformation of raw user payloads into
 * Amazon SP-API compatible structures based on dynamically fetched schemas.
 */
readonly class AmazonPayloadTransformerV2
{
    public function __construct(
        private AmazonSchemaServiceV2 $schemaService,
        private AmazonSchemaResolverV2 $schemaResolver,
        private SchemaValidator $schemaValidator,
        private TransformerResolver $transformerResolver,
        private SchemaWalker $schemaWalker
    ) {}

    /**
     * Builds the final Amazon payload by loading the schema, validating the raw payload,
     * and routing each field to its appropriate structural transformer.
     *
     * @param object $shop
     * @param string $productType
     * @param array $payload
     * @param AmazonTransformerConfig $config
     * @return array
     * @throws RuntimeException
     */

    
    public function build(
        object $shop,
        string $productType,
        array $payload,
        AmazonTransformerConfig $config
    ): array {
        Log::info('Schema loading started', [
            'product_type' => $productType
        ]);

        $schemaData = $this->schemaService->getCachedSchema($shop, $productType);

        if (!isset($schemaData['real_schema']) || !isset($schemaData['real_schema']['properties'])) {
            throw new RuntimeException('Schema root properties are unavailable. The schema must be resolved before payload transformation.');
        }

        // $realSchema = $schemaData['real_schema'];

        $resolvedSchema = $this->schemaResolver->resolve(
            $schemaData['real_schema']
        );

        Log::info('RAW SCHEMA', [
            'property_count' => count($schemaData['real_schema']['properties'] ?? [])
        ]);

        Log::info('RESOLVED SCHEMA', [
            'property_count' => count($resolvedSchema['properties'] ?? [])
        ]);

        Log::info('Schema Resolution Summary', [
            'raw_has_properties' => isset($schemaData['real_schema']['properties']),
            'resolved_has_properties' => isset($resolvedSchema['properties']),
            'raw_keys' => array_keys($schemaData['real_schema']),
            'resolved_keys' => array_keys($resolvedSchema),
        ]);
        // $schemaProperties = $realSchema['properties'];

        // $schemaProperties = $resolvedSchema['properties'];

        Log::info('Payload validation started', [
            'product_type' => $productType
        ]);

        try {

            $this->schemaValidator->validate(
                $resolvedSchema,
                $payload
            );

            Log::info('Payload validation passed');
        } catch (\Throwable $e) {

            Log::warning('V2 validation skipped', [
                'message' => $e->getMessage(),
            ]);
        }

        Log::info('SCHEMA WALKER START', [
            'product_type' => $productType,
        ]);

        //     $transformedPayload = [];

        Log::info('RAW PAYLOAD BEFORE SCHEMA WALKER 🔴', [
            'payload' => $payload,
        ]);

        $walkerPayload = $this->schemaWalker->walk(
            $resolvedSchema,
            $payload,
            $config
        );

        Log::info('SCHEMA WALKER OUTPUT', [
            'payload' => $walkerPayload,
        ]);

        //     foreach ($payload as $field => $value) {
        //         if ($this->isEmpty($value)) {
        //             continue;
        //         }

        //         Log::info('OLD TRANSFORMER FINISHED');

        //         if (!array_key_exists($field, $schemaProperties)) {
        //             Log::warning('Skipping unknown field', [
        //                 'field' => $field
        //             ]);
        //             continue;
        //         }

        //         Log::info('Processing field', [
        //             'field' => $field
        //         ]);

        //         $fieldSchema = $schemaProperties[$field];
        //         $transformer = $this->transformerResolver->resolve($fieldSchema);

        //         Log::info('Selected transformer', [
        //             'field' => $field,
        //             'transformer' => $transformer::class
        //         ]);

        //         $transformedPayload[$field] = $transformer->transform(
        //             $fieldSchema,
        //             $value,
        //             $config
        //         );
        //     }

        //     Log::info('Payload build completed', [
        //         'product_type' => $productType,
        //         'transformed_field_count' => count($transformedPayload)
        //     ]);

        //     return $transformedPayload;

        Log::info('SCHEMA WALKER FINAL PAYLOAD', [
            'payload' => $walkerPayload,
        ]);
        return $walkerPayload;
    }
    // }

    /**
     * Determines if a value is considered empty for transformation skipping.
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
