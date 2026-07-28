<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIResponseValidator
{
    /**
     * Validates and normalizes AI-generated JSON against Amazon Schema classifications.
     *
     * @param array $aiData The raw associative array output from the AI.
     * @param array $classification The structured schema metadata.
     * @return array{success: bool, data: array, errors: array, warnings: array}
     */
    public function validate(array $aiData, array $classification): array
    {
        $errors        = [];
        $warnings      = [];
        $data          = [];
        $invalidFields = [];

        $maps = $this->buildValidationMaps($classification);

        // Normalize keys to prevent duplicate/case-mismatch issues (e.g., 'Brand' vs 'brand')
        $normalizedAiData = [];
        foreach ($aiData as $rawKey => $value) {
            $lowerKey = strtolower((string)$rawKey);
            $trueKey  = $maps['keyMap'][$lowerKey] ?? $rawKey;
            $normalizedAiData[$trueKey] = $value;
        }

        foreach ($normalizedAiData as $key => $rawValue) {
            // Recursive cleanup to remove deep nulls, empty strings, and empty arrays
            $value = $this->recursiveCleanup($rawValue);

            if (!isset($maps['valid'][$key])) {
                if (!isset($maps['hidden'][$key]) && !isset($maps['readonly'][$key])) {
                    $warnings[] = "Field '{$key}' is unknown and was removed.";
                    $invalidFields[] = $key;
                }
                continue;
            }

            if (isset($maps['hidden'][$key])) {
                $warnings[] = "Field '{$key}' is hidden and was removed.";
                $invalidFields[] = $key;
                continue;
            }

            if (isset($maps['readonly'][$key])) {
                $warnings[] = "Field '{$key}' is read-only and was removed.";
                $invalidFields[] = $key;
                continue;
            }

            if ($this->isEmpty($value)) {
                continue;
            }

            $expectedType = $maps['valid'][$key]['type'] ?? 'string';

            $typeError = $this->validateDatatype($key, $value, $expectedType);
            if ($typeError) {
                $errors[] = $typeError;
                $invalidFields[] = $key;
                continue;
            }

            if (!empty($maps['enums'][$key])) {
                $enumError = $this->validateEnum($key, $value, $maps['enums'][$key]);
                if ($enumError) {
                    $errors[] = $enumError;
                    $invalidFields[] = $key;
                    continue;
                }
            }

            if (!empty($maps['patterns'][$key])) {
                $patternError = $this->validatePattern($key, $value, $maps['patterns'][$key]);
                if ($patternError) {
                    $errors[] = $patternError;
                    $invalidFields[] = $key;
                    continue;
                }
            }

            $data[$key] = $this->normalizeValue($value, $expectedType);
        }

        foreach ($maps['required'] as $reqKey) {
            if (!isset($data[$reqKey]) || $this->isEmpty($data[$reqKey])) {
                $errors[] = "Required field '{$reqKey}' is missing or empty.";
                $invalidFields[] = $reqKey;
            }
        }

        if (!empty($invalidFields)) {
            $warnings[] = "Invalid or rejected fields detected: " . implode(', ', array_unique($invalidFields));
        }

        return [
            'success'  => empty($errors),
            'data'     => $data,
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Transforms structured classification into flat, optimized O(1) lookup maps.
     */
    private function buildValidationMaps(array $classification): array
    {
        $maps = [
            'valid'    => [],
            'required' => [],
            'hidden'   => [],
            'readonly' => [],
            'enums'    => [],
            'patterns' => [],
            'keyMap'   => [], // Maps lowercase key to true schema key
        ];

        // Map Hidden & Readonly fields
        foreach (['hidden_fields' => 'hidden', 'readonly_fields' => 'readonly'] as $sourceKey => $mapTarget) {
            foreach ($classification[$sourceKey] ?? [] as $field) {
                $key = $field['key'];
                $maps[$mapTarget][$key] = true;
                $maps['keyMap'][strtolower($key)] = $key;
            }
        }

        // Map Valid & Required fields (excluding readonly)
        $validGroups = ['required_fields', 'recommended_fields', 'optional_fields'];
        foreach ($validGroups as $group) {
            foreach ($classification[$group] ?? [] as $field) {
                $key = $field['key'];
                $maps['keyMap'][strtolower($key)] = $key;
                
                if (isset($maps['readonly'][$key])) {
                    continue; 
                }

                $maps['valid'][$key] = $field;
                
                if ($group === 'required_fields') {
                    $maps['required'][] = $key;
                }
            }
        }

        // Map Enums
        foreach ($classification['enum_fields'] ?? [] as $field) {
            $maps['enums'][$field['key']] = array_column($field['values'] ?? [], 'value');
        }

        // Map Patterns
        foreach ($classification['pattern_fields'] ?? [] as $field) {
            if (!empty($field['pattern'])) {
                $maps['patterns'][$field['key']] = $field['pattern'];
            }
        }

        return $maps;
    }

    /**
     * Recursively traverses and removes deeply nested empty values.
     */
    private function recursiveCleanup(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $k => $v) {
            $cleaned = $this->recursiveCleanup($v);
            if ($this->isEmpty($cleaned)) {
                unset($value[$k]);
            } else {
                $value[$k] = $cleaned;
            }
        }

        return $value;
    }

    /**
     * Determines if a value is effectively empty and should be skipped.
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Ensures the AI generated the correct data structure for the field.
     */
    private function validateDatatype(string $key, mixed $value, string $expectedType): ?string
    {
        return match (strtolower($expectedType)) {
            'array', 'group', 'measurement', 'dimensions', 'currency' => 
                !is_array($value) ? "Field '{$key}' expects a structured array or group." : null,
                
            'boolean' => 
                !is_bool($value) && !in_array(strtolower((string)$value), ['true', 'false', '1', '0'], true) 
                ? "Field '{$key}' expects a boolean." : null,
                
            'number', 'float', 'integer', 'int' => 
                !is_numeric($value) ? "Field '{$key}' expects a numeric value." : null,
                
            'url', 'image' => 
                filter_var($value, FILTER_VALIDATE_URL) === false ? "Field '{$key}' expects a valid URL." : null,
                
            'date', 'datetime' => 
                strtotime((string)$value) === false ? "Field '{$key}' expects a valid date/time string." : null,
                
            default => 
                !is_scalar($value) && !is_array($value) ? "Field '{$key}' received an invalid datatype." : null,
        };
    }

    /**
     * Ensures the value strictly belongs to the allowed Amazon enum list.
     */
    private function validateEnum(string $key, mixed $value, array $validValues): ?string
    {
        if (empty($validValues)) {
            return null;
        }

        $valuesToCheck = is_array($value) ? $value : [$value];
        $validValuesMap = array_map('strval', $validValues);

        foreach ($valuesToCheck as $v) {
            if ($this->isEmpty($v)) continue;
            
            if (!is_scalar($v) || !in_array((string)$v, $validValuesMap, true)) {
                $allowed = implode(', ', $validValues);
                return "Field '{$key}' contains invalid value '{$v}'. Allowed: {$allowed}";
            }
        }

        return null;
    }

    /**
     * Safely checks the value against Amazon's JSON schema Regex patterns.
     */
    private function validatePattern(string $key, mixed $value, string $pattern): ?string
    {
        if (!is_string($value) || $value === '') {
            return null; 
        }

        // Wrap the raw JSON Schema regex safely in PHP delimiters
        $regex = '~' . str_replace('~', '\~', $pattern) . '~u';

        try {
            if (preg_match($regex, $value) === 0) {
                return "Field '{$key}' does not match the required pattern format.";
            }
        } catch (\Throwable $e) {
            Log::warning("Malformed regex pattern for Amazon field", [
                'field'   => $key,
                'pattern' => $pattern,
                'error'   => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Standardizes strings, casts native booleans, and casts native numerics.
     */
    private function normalizeValue(mixed $value, string $type): mixed
    {
        if ($this->isEmpty($value)) {
            return $value;
        }

        $type = strtolower($type);

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (in_array($type, ['integer', 'int'], true)) {
            return (int) $value;
        }

        if (in_array($type, ['number', 'float'], true)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            // Recursively trim array strings
            array_walk_recursive($value, function (&$item) {
                if (is_string($item)) {
                    $item = trim($item);
                }
            });
        }

        return $value;
    }
}