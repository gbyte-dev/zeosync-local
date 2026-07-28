<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;
use App\DTOs\TraversalContext;
use Illuminate\Support\Facades\Log;

readonly class SchemaWalker
{
    public function __construct(
        private TransformerResolver $transformerResolver
    ) {}

    /**
     * Recursively walks the resolved schema and formats the payload.
     */
    public function walk(
        array $schema,
        mixed $payload,
        AmazonTransformerConfig $config,
        ?TraversalContext $context = null
    ): mixed {
        $context ??= new TraversalContext();

        if ($this->isEmpty($payload)) {
            return $payload;
        }

        /*
        |--------------------------------------------------------------------------
        | Object
        |--------------------------------------------------------------------------
        */

        if ($this->isObjectSchema($schema, $payload)) {
            return $this->walkObject(
                $schema,
                $payload,
                $config,
                $context
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Array
        |--------------------------------------------------------------------------
        */

        if ($this->isArraySchema($schema)) {
            return $this->walkArray(
                $schema,
                $payload,
                $config,
                $context
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Primitive
        |--------------------------------------------------------------------------
        */


        // $transformer = $this->transformerResolver
        //     ->resolveLeafTransformer($schema, $context);

        // Log::info('LEAF TRANSFORMER', [
        //     'schema_type' => $schema['type'] ?? null,
        //     'payload' => $payload,
        //     'transformer' => get_class($transformer),
        //     'path' => $context->path,
        //     'field' => $context->field,
        //     'parentField' => $context->parentField,
        //     'depth' => $context->depth,
        // ]);

        // return $transformer->transform(
        //     $schema,
        //     $payload,
        //     $config
        // );

        return $payload;
    }

    /**
     * Walk object.
     */
    private function walkObject(
        array $schema,
        mixed $payload,
        AmazonTransformerConfig $config,
        TraversalContext $context
    ): mixed {

        if (!is_array($payload)) {
            return $payload;
        }

        $properties = $schema['properties'] ?? [];

        $result = [];

        foreach ($payload as $key => $value) {

            if (array_key_exists($key, $properties)) {

                $result[$key] = $this->walk(
                    $properties[$key],
                    $value,
                    $config,
                    $context->child((string) $key)
                );
            } else {

                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Walk array.
     */
    private function walkArray(
        array $schema,
        mixed $payload,
        AmazonTransformerConfig $config,
        TraversalContext $context
    ): array {

        $itemSchema = $schema['items'] ?? [];

        $payloadList = (
            is_array($payload)
            && array_is_list($payload)
        )
            ? $payload
            : [$payload];

        $result = [];

        foreach ($payloadList as $index => $item) {

            $result[] = $this->walk(
                $itemSchema,
                $item,
                $config,
                $context->child((string) $index)
            );
        }

        return $result;
    }

    /**
     * Object schema.
     */
    private function isObjectSchema(
        array $schema,
        mixed $payload
    ): bool {

        $isObject = isset($schema['properties'])
            || (($schema['type'] ?? '') === 'object');

        return $isObject
            && is_array($payload)
            && $this->isAssoc($payload);
    }

    /**
     * Array schema.
     */
    private function isArraySchema(
        array $schema
    ): bool {

        return ($schema['type'] ?? '') === 'array';
    }

    /**
     * Associative array.
     */
    private function isAssoc(
        array $array
    ): bool {

        if ($array === []) {
            return false;
        }

        return array_keys($array)
            !== range(0, count($array) - 1);
    }

    /**
     * Empty value.
     */
    private function isEmpty(
        mixed $value
    ): bool {

        return $value === null
            || $value === ''
            || $value === [];
    }
}
