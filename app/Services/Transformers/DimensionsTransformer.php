<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;

class DimensionsTransformer extends AbstractPayloadTransformer
{
    /**
     * Determine whether the schema represents a dimensions object.
     */
    public function matches(array $schema): bool
    {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        return isset(
            $properties['length'],
            $properties['width'],
            $properties['height']
        );
    }

    /**
     * Transform into Amazon dimensions structure.
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {

        return array_map(function ($item) use (
            $schema,
            $config
        ) {

            $result = [
                'length' => $this->formatMeasurement(
                    $item['length'] ?? null,
                    $config
                ),
                'width' => $this->formatMeasurement(
                    $item['width'] ?? null,
                    $config
                ),
                'height' => $this->formatMeasurement(
                    $item['height'] ?? null,
                    $config
                ),
            ];

            return $this->injectDefaults(
                $result,
                $schema,
                $config
            );
        }, $this->normalizeArray($value));
    }

    /**
     * Format a single measurement.
     */
    private function formatMeasurement(
        mixed $data,
        AmazonTransformerConfig $config
    ): array {

        if (is_array($data)) {
            return [
                'value' => (float) ($data['value'] ?? 0),
                'unit'  => $data['unit'] ?? $config->measurementUnit,
            ];
        }

        return [
            'value' => (float) $data,
            'unit'  => $config->measurementUnit,
        ];
    }
}
