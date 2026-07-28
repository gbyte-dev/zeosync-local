<?php

declare(strict_types=1);

namespace App\Services\Validators;

use Illuminate\Validation\ValidationException;

/**
 * Validates an Amazon SP-API payload strictly against a Product Type Definition (PTD) schema.
 * * This class is strictly responsible for validation. It does not mutate, transform,
 * or inject defaults into the payload. It relies entirely on the provided schema structure
 * and uses no hardcoded business logic or field names.
 */
readonly class SchemaValidator
{
    /**
     * Validates the payload against the provided Amazon schema.
     *
     * @param array $schema The Amazon PTD schema definition.
     * @param array $payload The raw payload to validate.
     * @throws ValidationException If the payload violates the schema rules.
     */
    public function validate(array $schema, array $payload): void
    {
        $errors = [];
        $schema = $this->unwrapSchema($schema);

        $this->validateNode($schema, $payload, '', $errors);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Recursively validates a single node in the payload against its schema definition.
     *
     * @param array $schema The schema definition for the current node.
     * @param mixed $value The payload value for the current node.
     * @param string $path The dot-notated path tracking the current depth.
     * @param array &$errors The accumulated array of validation errors.
     */
    private function validateNode(array $schema, mixed $value, string $path, array &$errors): void
    {
        // Skip validation for empty values. 
        // Missing required fields are handled by the parent object's required constraint.
        if ($this->isEmpty($value)) {
            return;
        }

        $schema = $this->unwrapSchema($schema);
        $type = $schema['type'] ?? null;

        if ($type === 'array' || isset($schema['items'])) {
            $this->validateArray($schema, $value, $path, $errors);
            return;
        }

        if ($type === 'object' || isset($schema['properties'])) {
            $this->validateObject($schema, $value, $path, $errors);
            return;
        }

        $this->validateScalarConstraints($schema, $value, $path, $errors);
    }

    /**
     * Validates an object, ensuring required properties exist and recursively validating children.
     *
     * @param array $schema
     * @param mixed $value
     * @param string $path
     * @param array &$errors
     */
    private function validateObject(array $schema, mixed $value, string $path, array &$errors): void
    {
        if (!is_array($value)) {
            $this->addError($errors, $path, 'Value must be a valid object.');
            return;
        }

        // Safely validate required properties, ensuring 'required' is a valid array
        $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
        foreach ($required as $requiredField) {
            if (!isset($value[$requiredField]) || $this->isEmpty($value[$requiredField])) {
                $errorPath = $path !== '' ? "{$path}.{$requiredField}" : $requiredField;
                $this->addError($errors, $errorPath, 'This field is required.');
            }
        }

        // Safely validate nested properties recursively, ensuring 'properties' is a valid array
        $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
        foreach ($value as $key => $childValue) {
            if (isset($properties[$key])) {
                $childPath = $path !== '' ? "{$path}.{$key}" : (string) $key;
                $this->validateNode($properties[$key], $childValue, $childPath, $errors);
            }
        }
    }

    /**
     * Validates an array, ensuring it is a sequential list, checking length limits, and validating items.
     *
     * @param array $schema
     * @param mixed $value
     * @param string $path
     * @param array &$errors
     */
    private function validateArray(array $schema, mixed $value, string $path, array &$errors): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            $this->addError($errors, $path, 'Value must be a valid list/array.');
            return;
        }

        $count = count($value);

        if (isset($schema['minItems']) && $count < $schema['minItems']) {
            $this->addError($errors, $path, "Must contain at least {$schema['minItems']} item(s).");
        }

        if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
            $this->addError($errors, $path, "Must contain no more than {$schema['maxItems']} item(s).");
        }

        // Safely retrieve items schema, enforcing strict typing to prevent TypeErrors
        $itemsSchema = isset($schema['items']) && is_array($schema['items']) ? $schema['items'] : [];
        foreach ($value as $index => $item) {
            $itemPath = $path !== '' ? "{$path}.{$index}" : (string) $index;
            $this->validateNode($itemsSchema, $item, $itemPath, $errors);
        }
    }

    /**
     * Validates scalar constraints such as enums, string lengths, numerical limits, and regex patterns.
     *
     * @param array $schema
     * @param mixed $value
     * @param string $path
     * @param array &$errors
     */
    private function validateScalarConstraints(array $schema, mixed $value, string $path, array &$errors): void
    {
        // Safely evaluate enums to prevent implode/in_array crashes on malformed schemas
        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $allowed = implode(', ', $schema['enum']);
            $this->addError($errors, $path, "Invalid value. Allowed values are: {$allowed}.");
        }

        if (is_string($value)) {
            $this->validateStringConstraints($schema, $value, $path, $errors);
        } elseif (is_numeric($value)) {
            $this->validateNumberConstraints($schema, (float) $value, $path, $errors);
        }
    }

    /**
     * Validates constraints specific to strings.
     *
     * @param array $schema
     * @param string $value
     * @param string $path
     * @param array &$errors
     */
    private function validateStringConstraints(array $schema, string $value, string $path, array &$errors): void
    {
        $length = mb_strlen($value);

        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            $this->addError($errors, $path, "Minimum length is {$schema['minLength']} characters.");
        }

        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            $this->addError($errors, $path, "Maximum length is {$schema['maxLength']} characters.");
        }

        if (
            isset($schema['pattern']) &&
            is_string($schema['pattern']) &&
            $schema['pattern'] !== ''
        ) {
            $pattern = '/' . str_replace('/', '\/', $schema['pattern']) . '/u';

            $result = @preg_match($pattern, $value);

            if ($result === false) {
                return;
            }

            if ($result === 0) {
                $this->addError(
                    $errors,
                    $path,
                    'Value does not match the required format.'
                );
            }
        }
    }

    /**
     * Validates constraints specific to numbers.
     *
     * @param array $schema
     * @param float $value
     * @param string $path
     * @param array &$errors
     */
    private function validateNumberConstraints(array $schema, float $value, string $path, array &$errors): void
    {
        if (
            isset($schema['minimum']) &&
            is_numeric($schema['minimum']) &&
            $value < (float) $schema['minimum']
        ) {
            $this->addError(
                $errors,
                $path,
                "Value must be at least {$schema['minimum']}."
            );
        }

        if (
            isset($schema['maximum']) &&
            is_numeric($schema['maximum']) &&
            $value > (float) $schema['maximum']
        ) {
            $this->addError(
                $errors,
                $path,
                "Value must not exceed {$schema['maximum']}."
            );
        }

        if (
            isset($schema['exclusiveMinimum']) &&
            is_numeric($schema['exclusiveMinimum']) &&
            $value <= (float) $schema['exclusiveMinimum']
        ) {
            $this->addError(
                $errors,
                $path,
                "Value must be strictly greater than {$schema['exclusiveMinimum']}."
            );
        }

        if (
            isset($schema['exclusiveMaximum']) &&
            is_numeric($schema['exclusiveMaximum']) &&
            $value >= (float) $schema['exclusiveMaximum']
        ) {
            $this->addError(
                $errors,
                $path,
                "Value must be strictly less than {$schema['exclusiveMaximum']}."
            );
        }
    }
    /**
     * Recursively unwraps composite JSON schema definitions (allOf, anyOf, oneOf).
     * Defensively checks that sub-schemas exist and are arrays before replacing.
     *
     * @param array $schema
     * @return array
     */
    private function unwrapSchema(array $schema): array
    {
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $subSchema) {
                if (is_array($subSchema)) {
                    $schema = array_replace_recursive($schema, $this->unwrapSchema($subSchema));
                }
            }
            unset($schema['allOf']);
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf']) && isset($schema['anyOf'][0]) && is_array($schema['anyOf'][0])) {
            // Take the first concrete schema in anyOf block safely
            $schema = array_replace_recursive($schema, $this->unwrapSchema($schema['anyOf'][0]));
            unset($schema['anyOf']);
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf']) && isset($schema['oneOf'][0]) && is_array($schema['oneOf'][0])) {
            // Take the first concrete schema in oneOf block safely
            $schema = array_replace_recursive($schema, $this->unwrapSchema($schema['oneOf'][0]));
            unset($schema['oneOf']);
        }

        return $schema;
    }

    /**
     * Determines if a value is considered empty for validation skipping.
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Safely pushes a validation error to the correct dot-notated path.
     *
     * @param array &$errors
     * @param string $path
     * @param string $message
     */
    private function addError(array &$errors, string $path, string $message): void
    {
        $key = $path === '' ? 'root' : $path;
        $errors[$key][] = $message;
    }
}
