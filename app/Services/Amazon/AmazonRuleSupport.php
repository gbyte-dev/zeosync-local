<?php

declare(strict_types=1);

namespace App\Services\Amazon;

class AmazonRuleSupport
{
    /**
     * Resolves the JSON path, supporting Amazon's indexed object arrays and null values.
     */
    public function resolvePath(array $payload, string $path): array
    {
        if ($path === '') {
            return [$payload];
        }

        $parts = explode('.', $path);
        $current = $payload;

        foreach ($parts as $part) {
            // Safely check for key existence without failing on null
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return [];
            }
            $current = $current[$part];
        }

        // If the target is an indexed list of objects, return them for iteration
        if (is_array($current) && array_is_list($current)) {
            return $current;
        }

        return [$current];
    }

    /**
     * Normalizes Amazon's nested value arrays into comparable scalars.
     */
    public function normalizeAmazonValue(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if (array_is_list($data) && count($data) === 1 && isset($data[0]['value'])) {
            return $data[0]['value'];
        }

        if (!array_is_list($data) && isset($data['value']) && count($data) === 1) {
            return $data['value'];
        }

        return $data;
    }

    /**
     * Safely compares scalar values, mitigating strict type mismatches ("1" vs 1).
     */
    public function compareValues(mixed $payloadValue, mixed $schemaValue): bool
    {
        if ($payloadValue === $schemaValue) {
            return true;
        }

        if (is_scalar($payloadValue) && is_scalar($schemaValue)) {
            if (is_numeric($payloadValue) && is_numeric($schemaValue)) {
                return (float) $payloadValue === (float) $schemaValue;
            }
            if (is_bool($payloadValue) || is_bool($schemaValue)) {
                return filter_var($payloadValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    === filter_var($schemaValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            return (string) $payloadValue === (string) $schemaValue;
        }

        return false;
    }

    /**
     * EVALUATION PASS: Silently checks if data matches conditions.
     * Generates NO errors.
     */
    public function isMatch(?array $schema, mixed $data): bool
    {
        if ($schema === null) {
            return true;
        }

        if (isset($schema['required'])) {
            if (!is_array($data)) return false;
            foreach ($schema['required'] as $req) {
                if (!array_key_exists($req, $data) || (is_array($data[$req]) && empty($data[$req]))) {
                    return false;
                }
            }
        }

        // FIXED: JSON Schema properties logic
        if (isset($schema['properties'])) {

            if (!is_array($data)) {
                return false;
            }

            foreach ($schema['properties'] as $key => $subSchema) {

                if (!array_key_exists($key, $data)) {
                    return false;
                }

                if (!$this->isMatch($subSchema, $data[$key])) {
                    return false;
                }
            }
        }

        if (isset($schema['contains'])) {
            if (!is_array($data) || empty($data)) return false;
            $matched = false;
            foreach ($data as $item) {
                if ($this->isMatch($schema['contains'], $item)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        if (isset($schema['items'])) {
            if (!is_array($data)) return false;
            foreach ($data as $item) {
                if (!$this->isMatch($schema['items'], $item)) {
                    return false;
                }
            }
        }

        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $subSchema) {
                if (!$this->isMatch($subSchema, $data)) return false;
            }
        }

        if (isset($schema['anyOf'])) {
            $matched = false;
            foreach ($schema['anyOf'] as $subSchema) {
                if ($this->isMatch($subSchema, $data)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        if (isset($schema['oneOf'])) {
            $matchCount = 0;
            foreach ($schema['oneOf'] as $subSchema) {
                if ($this->isMatch($subSchema, $data)) {
                    $matchCount++;
                }
            }
            if ($matchCount !== 1) return false;
        }

        if (isset($schema['not'])) {
            if ($this->isMatch($schema['not'], $data)) {
                return false;
            }
        }

        $normalizedData = $this->normalizeAmazonValue($data);

        if (isset($schema['enum'])) {
            $matched = false;
            foreach ($schema['enum'] as $allowedValue) {
                if ($this->compareValues($normalizedData, $allowedValue)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        if (isset($schema['const'])) {
            if (!$this->compareValues($normalizedData, $schema['const'])) {
                return false;
            }
        }

        if (isset($schema['minItems']) && (!is_array($data) || count($data) < $schema['minItems'])) {
            return false;
        }

        if (isset($schema['maxItems']) && (is_array($data) && count($data) > $schema['maxItems'])) {
            return false;
        }

        if (isset($schema['multipleOf']) && (!is_numeric($normalizedData) || fmod((float)$normalizedData, (float)$schema['multipleOf']) != 0)) {
            return false;
        }

        return true;
    }
}
