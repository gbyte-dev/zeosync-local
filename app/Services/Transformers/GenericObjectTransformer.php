<?php

declare(strict_types=1);

namespace App\Services\Transformers;
use Illuminate\Support\Facades\Log;

use App\DTOs\AmazonTransformerConfig;
use RuntimeException;

final class GenericObjectTransformer extends AbstractPayloadTransformer
{
    private ?TransformerResolver $resolver = null;

    public function setResolver(TransformerResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function matches(array $schema): bool
    {
        // Direct object schema
        if (($schema['type'] ?? null) === 'object') {
            return true;
        }

        // Array whose items are objects
        if (
            ($schema['type'] ?? null) === 'array'
            && isset($schema['items'])
            && is_array($schema['items'])
            && (($schema['items']['type'] ?? null) === 'object')
        ) {
            return true;
        }

        return false;
    }

    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {
        if (!$this->resolver) {
            throw new RuntimeException('TransformerResolver has not been initialized. Call setResolver() first.');
        }

        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $properties = $schema['properties'] ?? ($schema['items']['properties'] ?? []);

        $expectsList = ($schema['type'] ?? null) === 'array';

        if ($expectsList) {
            return array_map(
                fn($item) => $this->processObject($item, $properties, $schema, $config),
                $this->normalizeArray($value)
            );
        }

        return $this->processObject($value, $properties, $schema, $config);
    }

    private function processObject(
        mixed $item,
        array $properties,
        array $schema,
        AmazonTransformerConfig $config
    ): array {

        if (!is_array($item)) {
            throw new RuntimeException(sprintf(
                'GenericObjectTransformer encountered a scalar value (%s). Object schemas require array payloads. Check payload validity or schema routing.',
                get_debug_type($item)
            ));
        }

        if ($this->isAssoc($item)) {

            $transformed = [];

            foreach ($item as $key => $val) {

                Log::info('OBJECT CHILD', [
                    'key'   => $key,
                    'value' => $val,
                ]);

                if ($val === null || $val === '' || $val === []) {
                    continue;
                }

                if (array_key_exists($key, $properties)) {

                    $childSchema = $properties[$key];

                    Log::info('OBJECT CHILD SCHEMA', [
                        'key'      => $key,
                        'type'     => $childSchema['type'] ?? null,
                        'keys'     => array_keys($childSchema),
                        'resolver' => get_class($this->resolver->resolve($childSchema)),
                    ]);

                    $transformer = $this->resolver->resolve($childSchema);

                    $result = $transformer->transform(
                        $childSchema,
                        $val,
                        $config
                    );

                    Log::info('OBJECT CHILD RESULT', [
                        'key'    => $key,
                        'result' => $result,
                    ]);

                    $transformed[$key] = $result;
                } else {

                    Log::warning('OBJECT CHILD UNKNOWN', [
                        'key'   => $key,
                        'value' => $val,
                    ]);

                    $transformed[$key] = $val;
                }
            }

            Log::info('OBJECT FINAL', [
                'object' => $transformed,
            ]);

            $item = $transformed;
        }

        $final = $this->injectDefaults(
            $item,
            $schema,
            $config
        );

        Log::info('OBJECT AFTER DEFAULTS', [
            'object' => $final,
        ]);

        return $final;
    }
}
