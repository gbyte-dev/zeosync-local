<?php

/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
| Currently, visibility is inferred from matched conditional property
| traversal because SmartForm initially renders only a subset of fields.
|
| If future Amazon PTD categories distinguish between "active schema"
| and "renderable UI fields", this visibility logic should be moved
| into a dedicated visibility resolver.
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Log;

class AmazonConditionalEvaluator
{
    public function __construct(
        private readonly AmazonRuleSupport $support
    ) {}

    /**
     * Evaluate conditional rules to produce UI state, without mutating the payload
     * or generating validation errors.
     *
     * @param array $rules Parsed rules.json array
     * @param array $payload The dynamic Amazon payload
     * @return array Array mapping 'visible', 'hidden', 'required', 'optional', and 'enumChanges'
     */
    public function evaluate(array $rules, array $payload): array
    {
        $state = [
            'visible' => [],
            'hidden' => [],
            'required' => [],
            'optional' => [],
            'enumChanges' => [],
        ];

        foreach ($rules as $rule) {
            $path = $rule['path'] ?? '';
            $contexts = $this->support->resolvePath($payload, $path);

            foreach ($contexts as $contextData) {
                // Pass base path to prevent indexed UI paths (e.g., shirt_size.0.body_type)
                $this->evaluateRule($rule, $contextData, $path, $state);
            }
        }

        \Log::info('FINAL REQUIRED', [
            'required' => array_keys($state['required']),
        ]);

        return [
            'visible' => array_keys($state['visible']),
            'hidden' => array_keys($state['hidden']),
            'required' => array_keys($state['required']),
            'optional' => array_keys($state['optional']),
            'enumChanges' => $state['enumChanges'],
        ];
    }

    /**
     * Strictly processes logic gating. 
     * The parent schema must never reach applySchema() if it is a routing node.
     */
    private function evaluateRule(?array $schema, mixed $data, string $pathContext, array &$state): void
    {
        if (empty($schema) || !is_array($schema)) {
            return;
        }

        \Log::info('======================================================');
        \Log::info('EVALUATE RULE START', [
            'path' => $pathContext,
            'data' => $data,
            'has_if' => isset($schema['if']),
            'has_then' => isset($schema['then']),
            'has_else' => isset($schema['else']),
            'has_allOf' => isset($schema['allOf']),
            'has_anyOf' => isset($schema['anyOf']),
            'has_oneOf' => isset($schema['oneOf']),
            'has_properties' => isset($schema['properties']),
            'has_enum' => isset($schema['enum']),
        ]);

        /*
    |--------------------------------------------------------------------------
    | IF / THEN / ELSE
    |--------------------------------------------------------------------------
    */

        if (array_key_exists('if', $schema)) {

            \Log::info('IF SCHEMA', [
                'path' => $pathContext,
                'if' => $schema['if'],
            ]);

            \Log::info('DATA FOR MATCH', [
                'path' => $pathContext,
                'data' => $data,
            ]);

            $matched = $this->support->isMatch(
                $schema['if'],
                $data
            );

            \Log::info('MATCH RESULT', [
                'path' => $pathContext,
                'matched' => $matched,
            ]);

            if ($matched) {

                \Log::info('ENTER THEN', [
                    'path' => $pathContext,
                ]);

                if (isset($schema['then'])) {

                    if (isset($schema['then']['properties'])) {

                        \Log::info('THEN PROPERTIES', [
                            'path' => $pathContext,
                            'properties' => array_keys(
                                $schema['then']['properties']
                            ),
                        ]);
                    }

                    if (isset($schema['then']['required'])) {

                        \Log::info('THEN REQUIRED', [
                            'path' => $pathContext,
                            'required' => $schema['then']['required'],
                        ]);
                    }

                    if (isset($schema['then']['enum'])) {

                        \Log::info('THEN ENUM', [
                            'path' => $pathContext,
                            'count' => count($schema['then']['enum']),
                            'sample' => array_slice(
                                $schema['then']['enum'],
                                0,
                                20
                            ),
                        ]);
                    }

                    $this->evaluateRule(
                        $schema['then'],
                        $data,
                        $pathContext,
                        $state
                    );
                }
            } else {

                \Log::info('ENTER ELSE', [
                    'path' => $pathContext,
                ]);

                if (isset($schema['else'])) {

                    if (isset($schema['else']['properties'])) {

                        \Log::info('ELSE PROPERTIES', [
                            'path' => $pathContext,
                            'properties' => array_keys(
                                $schema['else']['properties']
                            ),
                        ]);
                    }

                    if (isset($schema['else']['required'])) {

                        \Log::info('ELSE REQUIRED', [
                            'path' => $pathContext,
                            'required' => $schema['else']['required'],
                        ]);
                    }

                    if (isset($schema['else']['enum'])) {

                        \Log::info('ELSE ENUM', [
                            'path' => $pathContext,
                            'count' => count($schema['else']['enum']),
                            'sample' => array_slice(
                                $schema['else']['enum'],
                                0,
                                20
                            ),
                        ]);
                    }

                    $this->evaluateRule(
                        $schema['else'],
                        $data,
                        $pathContext,
                        $state
                    );
                }
            }

            \Log::info('EXIT IF BLOCK', [
                'path' => $pathContext,
            ]);

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | allOf
    |--------------------------------------------------------------------------
    */

        if (isset($schema['allOf'])) {

            \Log::info('ENTER allOf', [
                'path' => $pathContext,
                'count' => count($schema['allOf']),
            ]);

            foreach ($schema['allOf'] as $index => $subSchema) {

                \Log::info('PROCESS allOf CHILD', [
                    'path' => $pathContext,
                    'index' => $index,
                ]);

                $this->evaluateRule(
                    $subSchema,
                    $data,
                    $pathContext,
                    $state
                );
            }

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | anyOf
    |--------------------------------------------------------------------------
    */

        if (isset($schema['anyOf'])) {

            \Log::info('ENTER anyOf', [
                'path' => $pathContext,
                'count' => count($schema['anyOf']),
            ]);

            foreach ($schema['anyOf'] as $index => $subSchema) {

                $match = $this->support->isMatch(
                    $subSchema,
                    $data
                );

                \Log::info('anyOf RESULT', [
                    'index' => $index,
                    'matched' => $match,
                ]);

                if ($match) {

                    $this->evaluateRule(
                        $subSchema,
                        $data,
                        $pathContext,
                        $state
                    );

                    break;
                }
            }

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | oneOf
    |--------------------------------------------------------------------------
    */

        if (isset($schema['oneOf'])) {

            \Log::info('ENTER oneOf', [
                'path' => $pathContext,
                'count' => count($schema['oneOf']),
            ]);

            foreach ($schema['oneOf'] as $index => $subSchema) {

                $match = $this->support->isMatch(
                    $subSchema,
                    $data
                );

                \Log::info('oneOf RESULT', [
                    'index' => $index,
                    'matched' => $match,
                ]);

                if ($match) {

                    $this->evaluateRule(
                        $subSchema,
                        $data,
                        $pathContext,
                        $state
                    );

                    break;
                }
            }

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | APPLY SCHEMA
    |--------------------------------------------------------------------------
    */

        \Log::info('APPLY SCHEMA', [
            'path' => $pathContext,
            'properties' => isset($schema['properties'])
                ? array_keys($schema['properties'])
                : [],
            'required' => $schema['required'] ?? [],
            'enum_count' => isset($schema['enum'])
                ? count($schema['enum'])
                : 0,
        ]);

        $this->applySchema(
            $schema,
            $data,
            $pathContext,
            $state
        );

        \Log::info('EVALUATE RULE END', [
            'path' => $pathContext,
        ]);
    }

    /**
     * Applies the validated active schema segment by extracting state elements
     * and delegating nested structures back to evaluateRule().
     */
    private function applySchema(array $schema, mixed $data, string $pathContext, array &$state): void
    {
        // Execute focused, single-responsibility extraction methods
        $this->extractRequired($schema, $pathContext, $state);
        $this->extractOptional($schema, $pathContext, $state);
        $this->extractHidden($schema, $pathContext, $state);
        $this->extractVisibility($schema, $pathContext, $state);
        $this->extractEnums($schema, $pathContext, $state);

        // Traverse down into nested object properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $subSchema) {
                if (!is_array($subSchema)) {
                    continue;
                }

                $newPath = $pathContext === '' ? (string) $key : "{$pathContext}.{$key}";
                $childData = (is_array($data) && array_key_exists($key, $data)) ? $data[$key] : null;

                $this->evaluateRule($subSchema, $childData, $newPath, $state);
            }
        }

        // Traverse down into array items
        if (isset($schema['items']) && is_array($schema['items'])) {
            // If data is absent, run once with null so UI can extract internal required/enums for rendering
            $itemsData = (is_array($data) && !empty($data)) ? $data : [null];

            foreach ($itemsData as $item) {
                // Items propagate the current un-indexed base pathContext
                $this->evaluateRule($schema['items'], $item, $pathContext, $state);
            }
        }

        // Traverse conditionally contained array items
        if (isset($schema['contains']) && is_array($schema['contains']) && is_array($data)) {
            foreach ($data as $item) {
                if ($this->support->isMatch($schema['contains'], $item)) {
                    $this->evaluateRule($schema['contains'], $item, $pathContext, $state);
                }
            }
        }
    }

    private function extractRequired(array $schema, string $pathContext, array &$state): void
    {
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $req) {
                if ($req === 'merchant_shipping_group') {
                    continue;
                }

                $fieldPath = $pathContext === '' ? (string) $req : "{$pathContext}.{$req}";

                $state['required'][$fieldPath] = true;
                $state['visible'][$fieldPath] = true;
            }
        }
    }

    private function extractOptional(array $schema, string $pathContext, array &$state): void
    {
        if (isset($schema['not']['required']) && is_array($schema['not']['required'])) {
            foreach ($schema['not']['required'] as $req) {
                $fieldPath = $pathContext === '' ? (string) $req : "{$pathContext}.{$req}";
                $state['optional'][$fieldPath] = true;
            }
        }
    }

    private function extractHidden(array $schema, string $pathContext, array &$state): void
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $subSchema) {
                $newPath = $pathContext === '' ? (string) $key : "{$pathContext}.{$key}";

                if ($subSchema === false || (is_array($subSchema) && isset($subSchema['not']) && empty($subSchema['not']))) {
                    $state['hidden'][$newPath] = true;
                }
            }
        }
    }

    private function extractVisibility(array $schema, string $pathContext, array &$state): void
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $subSchema) {
                $newPath = $pathContext === '' ? (string) $key : "{$pathContext}.{$key}";

                // Do not mark property as visible if it explicitly signals it is hidden
                if ($subSchema === false || (is_array($subSchema) && isset($subSchema['not']) && empty($subSchema['not']))) {
                    continue;
                }

                $state['visible'][$newPath] = true;
            }
        }
    }

    private function extractEnums(array $schema, string $pathContext, array &$state): void
    {
        if (!isset($schema['enum'])) {
            return;
        }

        Log::info('ENUM', [
            'path' => $pathContext,
            'count' => count($schema['enum']),
            'first' => array_slice($schema['enum'], 0, 5)
        ]);

        if ($pathContext !== '') {
            $state['enumChanges'][$pathContext] = $schema['enum'];
        }
    }
}
