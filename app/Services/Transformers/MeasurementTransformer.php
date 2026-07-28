<?php

declare(strict_types=1);

namespace App\Services\Transformers;
use Illuminate\Support\Facades\Log;
use App\DTOs\AmazonTransformerConfig;

class MeasurementTransformer extends AbstractPayloadTransformer
{
    /**
     * Determine whether the schema represents a measurement.
     */
    public function matches(array $schema): bool
    {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        return isset($properties['unit'])
            && !isset($properties['length']);
    }

    /**
     * Transform into Amazon measurement structure.
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {

        Log::info('MEASUREMENT START', [
            'input' => $value,
        ]);

        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        // Use first allowed enum as schema default if available.
        $schemaUnit = $properties['unit']['enum'][0] ?? null;

        return array_map(function ($item) use (
            $schema,
            $config,
            $schemaUnit
        ) {

            $amount = is_array($item)
                ? ($item['value'] ?? 0)
                : $item;

            $unit = (
                is_array($item)
                && !empty($item['unit'])
            )
                ? $item['unit']
                : ($schemaUnit ?? $config->measurementUnit);

            $result = [
                'value' => (float) $amount,
                'unit'  => $unit,
            ];
            Log::info('MEASUREMENT RESULT', [
                'result' => $result,
            ]);

            return $this->injectDefaults(
                $result,
                $schema,
                $config
            );
        }, $this->normalizeArray($value));
    }
}
