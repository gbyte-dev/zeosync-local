<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;
use Illuminate\Support\Facades\Log;

class GenericTransformer extends AbstractPayloadTransformer
{
    /**
     * Final fallback transformer.
     */
    public function matches(array $schema): bool
    {
        return true;
    }

    /**
     * Transform any scalar into Amazon's standard value wrapper.
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {

        Log::info('========== GENERIC TRANSFORM START ==========', [
            'schema_type' => $schema['type'] ?? null,
            'schema_keys' => array_keys($schema),
            'raw_input' => $value,
            'raw_input_type' => get_debug_type($value),
            'is_array' => is_array($value),
            'is_assoc' => is_array($value) ? $this->isAssoc($value) : false,
        ]);

        $normalized = $this->normalizeArray($value);

        Log::info('GENERIC NORMALIZED ARRAY', [
            'normalized' => $normalized,
        ]);

        $result = array_map(function ($item) use (
            $schema,
            $config
        ) {

            Log::info('GENERIC ITEM BEFORE WRAP', [
                'item' => $item,
                'item_type' => get_debug_type($item),
                'has_value_key' => is_array($item) && array_key_exists('value', $item),
            ]);

            $wrapped = (
                is_array($item)
                && array_key_exists('value', $item)
            )
                ? $item
                : [
                    'value' => $item,
                ];

            Log::info('GENERIC ITEM AFTER WRAP', [
                'wrapped' => $wrapped,
            ]);

            $final = $this->injectDefaults(
                $wrapped,
                $schema,
                $config
            );

            Log::info('GENERIC ITEM AFTER DEFAULTS', [
                'final' => $final,
            ]);

            return $final;

        }, $normalized);

        Log::info('========== GENERIC TRANSFORM END ==========', [
            'result' => $result,
        ]);

        return $result;
    }
}