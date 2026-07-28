<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;

abstract class AbstractPayloadTransformer implements PayloadTransformerInterface
{
    /**
     * Ensure every value is returned as a list.
     */
    protected function normalizeArray(mixed $value): array
    {
        return (is_array($value) && array_is_list($value))
            ? $value
            : [$value];
    }

    /**
     * Inject schema-driven defaults like marketplace_id and language_tag.
     */
    protected function injectDefaults(
        array $item,
        array $schema,
        AmazonTransformerConfig $config
    ): array {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        if (
            isset($properties['marketplace_id']) &&
            empty($item['marketplace_id'])
        ) {
            $item['marketplace_id'] = $config->marketplaceId;
        }

        if (
            isset($properties['language_tag']) &&
            empty($item['language_tag'])
        ) {
            $item['language_tag'] = $config->languageTag;
        }

        return $item;
    }

    /**
     * Determine whether an array is associative.
     */
    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}