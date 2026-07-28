<?php

declare(strict_types=1);

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Log;

class AmazonRuleEvaluator
{
    public function __construct(
        private readonly AmazonRuleSupport $support
    ) {}

    /**
     * Validate an Amazon SP-API payload against parsed PTD rules.
     *
     * @param array $rules Parsed rules.json array
     * @param array $payload The dynamic Amazon payload
     * @return array Contains 'errors' array with path-specific validation failures
     */
    public function validate(array $rules, array $payload): array
    {
        $errors = [];

        foreach ($rules as $rule) {
            $path = $rule['path'] ?? '';
            $contexts = $this->support->resolvePath($payload, $path);

            foreach ($contexts as $index => $contextData) {
                // If path resolves to an indexed list (e.g., shirt_size arrays), append the index
                $pathPrefix = $path === '' ? '' : "{$path}.{$index}";
                $this->enforce($rule, $contextData, $pathPrefix, $errors);
            }
        }

        return ['errors' => $errors];
    }

    /**
     * ENFORCEMENT PASS: Generates validation errors for failed branches.
     */
    private function enforce(?array $schema, mixed $data, string $pathContext, array &$errors): void
    {
        if ($schema === null) {
            return;
        }

        // 1. Process Logic Branching
        if (array_key_exists('if', $schema)) {
            if ($this->support->isMatch($schema['if'], $data)) {
                Log::info('RULE MATCHED: THEN branch', ['path' => $pathContext]);
                if (isset($schema['then'])) {
                    $this->enforce($schema['then'], $data, $pathContext, $errors);
                }
            } else {
                Log::info('RULE FAILED: ELSE branch', ['path' => $pathContext]);
                if (isset($schema['else'])) {
                    $this->enforce($schema['else'], $data, $pathContext, $errors);
                }
            }

            // Do not evaluate structural elements alongside 'if' unless explicitly defined
            if (!array_intersect_key($schema, array_flip(['required', 'properties', 'enum', 'const', 'allOf', 'anyOf', 'oneOf']))) {
                return;
            }
        }

        // 2. Structural Requirements
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $req) {
                // Ignore Amazon shipping template
                if ($req === 'merchant_shipping_group') {
                    continue;
                }
                $fieldPath = $pathContext === '' ? $req : "{$pathContext}.{$req}";

                // Uses array_key_exists to safely validate null representations
                if (!is_array($data) || !array_key_exists($req, $data) || (is_array($data[$req]) && empty($data[$req]))) {
                    $msg = "Field '{$req}' is required.";
                    Log::info('VALIDATION ERROR', ['field' => $fieldPath, 'message' => $msg]);
                    $errors[] = ['field' => $fieldPath, 'message' => $msg];
                }
            }
        }

        if (isset($schema['not']['required'])) {
            foreach ($schema['not']['required'] as $req) {
                $fieldPath = $pathContext === '' ? $req : "{$pathContext}.{$req}";

                if (is_array($data) && array_key_exists($req, $data) && !(is_array($data[$req]) && empty($data[$req]))) {
                    $msg = "Field '{$req}' must not be present under current configuration.";
                    Log::info('VALIDATION ERROR', ['field' => $fieldPath, 'message' => $msg]);
                    $errors[] = ['field' => $fieldPath, 'message' => $msg];
                }
            }
        }

        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $key => $subSchema) {
                $newPath = $pathContext === '' ? $key : "{$pathContext}.{$key}";
                // Missing properties are safely ignored. Null properties are evaluated.
                if (array_key_exists($key, $data)) {
                    $this->enforce($subSchema, $data[$key], $newPath, $errors);
                }
            }
        }

        if (isset($schema['items']) && is_array($data)) {
            foreach ($data as $index => $item) {
                $newPath = $pathContext === '' ? (string)$index : "{$pathContext}.{$index}";
                $this->enforce($schema['items'], $item, $newPath, $errors);
            }
        }

        // 3. Logical Combinations
        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $subSchema) {
                $this->enforce($subSchema, $data, $pathContext, $errors);
            }
        }

        if (isset($schema['anyOf'])) {
            if (!$this->support->isMatch($schema, $data)) {
                $msg = "Payload does not match any allowed structural variations (anyOf).";
                Log::info('VALIDATION ERROR', ['field' => $pathContext, 'message' => $msg]);
                $errors[] = ['field' => $pathContext, 'message' => $msg];
            }
        }

        if (isset($schema['oneOf'])) {
            $matchCount = 0;
            foreach ($schema['oneOf'] as $subSchema) {
                if ($this->support->isMatch($subSchema, $data)) {
                    $matchCount++;
                }
            }
            if ($matchCount !== 1) {
                $msg = "Payload must match exactly one allowed structural variation (oneOf). Matched {$matchCount}.";
                Log::info('VALIDATION ERROR', ['field' => $pathContext, 'message' => $msg]);
                $errors[] = ['field' => $pathContext, 'message' => $msg];
            }
        }

        // 4. Value Constraints (Enum and Const)
        $normalizedData = $this->support->normalizeAmazonValue($data);

        if (isset($schema['enum'])) {
            $matched = false;
            foreach ($schema['enum'] as $allowedValue) {
                if ($this->support->compareValues($normalizedData, $allowedValue)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $errorVal = is_scalar($normalizedData) ? $normalizedData : gettype($normalizedData);
                $msg = "Value '{$errorVal}' is invalid. Allowed: " . implode(', ', $schema['enum']);
                Log::info('VALIDATION ERROR', ['field' => $pathContext, 'message' => $msg]);
                $errors[] = ['field' => $pathContext, 'message' => $msg];
            }
        }

        if (isset($schema['const'])) {
            if (!$this->support->compareValues($normalizedData, $schema['const'])) {
                $errorVal = is_scalar($normalizedData) ? $normalizedData : gettype($normalizedData);
                $msg = "Value '{$errorVal}' must exactly match '{$schema['const']}'.";
                Log::info('VALIDATION ERROR', ['field' => $pathContext, 'message' => $msg]);
                $errors[] = ['field' => $pathContext, 'message' => $msg];
            }
        }

        // 5. Array Length & Math Constraints
        if (isset($schema['minItems']) && (!is_array($data) || count($data) < $schema['minItems'])) {
            $errors[] = ['field' => $pathContext, 'message' => "Requires at least {$schema['minItems']} item(s)."];
        }

        if (isset($schema['maxItems']) && (is_array($data) && count($data) > $schema['maxItems'])) {
            $errors[] = ['field' => $pathContext, 'message' => "Exceeds maximum allowed items ({$schema['maxItems']})."];
        }

        if (isset($schema['multipleOf']) && is_numeric($normalizedData)) {
            if (fmod((float)$normalizedData, (float)$schema['multipleOf']) != 0) {
                $errors[] = ['field' => $pathContext, 'message' => "Value must be a multiple of {$schema['multipleOf']}."];
            }
        }
    }
}
