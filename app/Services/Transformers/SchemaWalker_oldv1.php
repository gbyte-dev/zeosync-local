<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;
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
        AmazonTransformerConfig $config
    ): mixed {

        if ($this->isEmpty($payload)) {
            return $payload;
        }

        /*
        |--------------------------------------------------------------------------
        | STOP CONDITION
        |--------------------------------------------------------------------------
        | Already transformed Amazon object.
        | Example:
        | [
        |     'value' => 'Cotton'
        | ]
        |
        | Don't recurse into value/language_tag/currency/etc.
        */

        if ($this->isAmazonValueObject($payload)) {

            Log::info('SCHEMA WALK STOP', [
                'payload' => $payload,
            ]);

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
                $config
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
                $config
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Primitive
        |--------------------------------------------------------------------------
        */

        $transformer = $this->transformerResolver
            ->resolveLeafTransformer($schema);

        Log::info('LEAF TRANSFORMER', [
            'schema_type' => $schema['type'] ?? null,
            'payload' => $payload,
            'transformer' => get_class($transformer),
        ]);

        return $transformer->transform(
            $schema,
            $payload,
            $config
        );
    }

    /**
     * Walk object.
     */
    private function walkObject(
        array $schema,
        mixed $payload,
        AmazonTransformerConfig $config
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
                    $config
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
        AmazonTransformerConfig $config
    ): array {

        $itemSchema = $schema['items'] ?? [];

        $payloadList = (
            is_array($payload)
            && array_is_list($payload)
        )
            ? $payload
            : [$payload];

        $result = [];

        foreach ($payloadList as $item) {

            $result[] = $this->walk(
                $itemSchema,
                $item,
                $config
            );
        }

        return $result;
    }

    /**
     * Detect Amazon wrapper objects.
     */
    private function isAmazonValueObject(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        if (!$this->isAssoc($payload)) {
            return false;
        }

        return array_key_exists('value', $payload);
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
