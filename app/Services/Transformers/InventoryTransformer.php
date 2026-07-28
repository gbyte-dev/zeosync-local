<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;
use Illuminate\Validation\ValidationException;

class InventoryTransformer extends AbstractPayloadTransformer
{
    /**
     * Determine whether the schema represents inventory.
     */
    public function matches(array $schema): bool
    {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        return isset($properties['fulfillment_channel_code'])
            || isset($properties['is_inventory_available']);
    }

    /**
     * Transform inventory payload.
     *
     * @throws ValidationException
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {

        $required = $schema['required']
            ?? ($schema['items']['required'] ?? []);

        return array_map(function ($item) use (
            $schema,
            $config,
            $required
        ) {

            $result = [
                'fulfillment_channel_code' =>
                    $item['fulfillment_channel_code']
                    ?? 'DEFAULT',
            ];

            if (
                is_array($item) &&
                array_key_exists('quantity', $item) &&
                $item['quantity'] !== ''
            ) {

                $result['quantity'] = (int) $item['quantity'];

            } elseif (
                is_array($item) &&
                array_key_exists('is_inventory_available', $item)
            ) {

                $result['is_inventory_available']
                    = (bool) $item['is_inventory_available'];

            } elseif (is_numeric($item)) {

                $result['quantity'] = (int) $item;

            } elseif (in_array('quantity', $required, true)) {

                throw ValidationException::withMessages([
                    'inventory' =>
                        'Quantity is required by the Amazon schema.',
                ]);
            }

            return $this->injectDefaults(
                $result,
                $schema,
                $config
            );

        }, $this->normalizeArray($value));
    }
}