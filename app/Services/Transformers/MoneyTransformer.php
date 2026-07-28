<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;

class MoneyTransformer extends AbstractPayloadTransformer
{
    /**
     * Determine whether the schema represents a monetary value.
     */
    public function matches(array $schema): bool
    {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        return isset($properties['currency']);
    }

    /**
     * Transform into Amazon money structure.
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {
        return array_map(function ($item) use ($schema, $config) {

            $amount = is_array($item)
                ? ($item['value'] ?? 0)
                : $item;

            $currency = (
                is_array($item)
                && !empty($item['currency'])
            )
                ? $item['currency']
                : $config->currency;

            $result = [
                'value'    => (float) $amount,
                'currency' => $currency,
            ];

            return $this->injectDefaults(
                $result,
                $schema,
                $config
            );

        }, $this->normalizeArray($value));
    }
}