<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * AmazonSchemaFilterService
 *
 * Read-only classification service for Amazon Product Type Definition (PTD) schemas.
 *
 * Walks schema properties recursively and exposes categorized field metadata:
 * required, recommended, optional, hidden, readonly, enum, and pattern.
 *
 * This is a pure classification layer — it does not render, persist, or transform data.
 */
class AmazonSchemaFilterService
{
    /** Internal Amazon serialization fields — never shown to the user. */
    private const INTERNAL_FIELDS = [
        'marketplace_id',
        'language_tag',
    ];

    /** Fields the Zeosync system auto-populates behind the scenes. */
    private const AUTO_POPULATED_FIELDS = [
        'child_parent_sku_relationship',
        'merchant_shipping_group',
    ];

    /** Compliance fields that clutter the form for typical listings. */
    private const COMPLIANCE_FIELDS = [
        'cpsia_cautionary_statement',
        'safety_data_sheet_url',
        'supplier_declared_dg_hz_regulation',
        'contains_battery_or_cell',
        'battery_contains_free_unabsorbed_liquid',
        'has_less_than_30_percent_state_of_charge',
        'is_battery_non_spillable',
        'ghs',
        'hazmat',
    ];

    /** Variation fields — only relevant when the product has children. */
    private const VARIATION_FIELDS = [
        'variation_theme',
        'parentage_level',
    ];

    /** Cached root schema for local $ref resolution. */
    private array $rootSchema = [];

    /**
     * Classify every property in the resolved schema into its category.
     *
     * @param array $schema The fully resolved schema (must have 'properties' key).
     * @return array{
     *     required_fields: array<int, array>,
     *     recommended_fields: array<int, array>,
     *     optional_fields: array<int, array>,
     *     hidden_fields: array<int, array>,
     *     readonly_fields: array<int, array>,
     *     enum_fields: array<int, array>,
     *     pattern_fields: array<int, array>,
     * }
     */
    public function classify(array $schema): array
    {
        $this->rootSchema = $schema;

        $properties = $schema['properties'] ?? [];

        if (empty($properties)) {
            Log::warning('AmazonSchemaFilterService: Schema has no properties.');
            return $this->emptyResult();
        }

        $rootRequired = $schema['required'] ?? [];
        $propertyGroups = $schema['propertyGroups'] ?? [];
        $allOfSections = $schema['allOf'] ?? [];

        // Build requirement lookup for root-level properties
        $requirementMap = $this->buildRequirementMap($propertyGroups, $rootRequired, $allOfSections);

        $result = $this->emptyResult();

        foreach ($properties as $key => $property) {
            if (!is_array($property)) {
                continue;
            }

            $this->classifyField(
                key: $key,
                property: $property,
                parentPath: '',
                parentKey: '',
                parentRequired: $rootRequired,
                requirementMap: $requirementMap,
                result: $result,
            );
        }

        return $result;
    }

    // Recursive Field Classification

    /**
     * Classify a single property and recursively walk its children.
     */
    private function classifyField(
        string $key,
        array $property,
        string $parentPath,
        string $parentKey,
        array $parentRequired,
        array $requirementMap,
        array &$result,
    ): void {
        $path = $parentPath === '' ? $key : "{$parentPath}.{$key}";

        // Resolve composition (allOf/anyOf/oneOf/items) and defensive $ref fallback
        $resolved = $this->resolveEffectiveProperty($property);
        $resolved = $this->resolveLocalRef($resolved);

        // Gather metadata from the fully resolved schema
        $title = $this->resolveTitle($key, $resolved);
        $type = $this->resolveFieldType($key, $resolved);
        $enumValues = $this->resolveEnum($resolved);
        $pattern = $this->resolvePattern($resolved);

        $isRequired = in_array($key, $parentRequired, true);
        $hiddenReason = $this->getHiddenReason($key, $property, $resolved);
        $isReadonly = !$hiddenReason && $this->isReadonlyField($key, $resolved);

        $children = $this->resolveChildKeys($resolved, $type);

        // Build the base field entry
        $entry = [
            'key'         => $key,
            'title'       => $title,
            'type'        => $type,
            'definition'  => $resolved,
            'path'        => $path,
            'parent'      => $parentKey,
            'children'    => $children,
            'is_required' => $isRequired,
            'is_hidden'   => $hiddenReason !== null,
            'is_readonly' => $isReadonly,
        ];

        // Hidden fields skip further classification
        if ($hiddenReason !== null) {
            $result['hidden_fields'][] = $entry + ['reason' => $hiddenReason];
            return;
        }

        // Readonly fields use a consistent 'reason' key
        if ($isReadonly) {
            $result['readonly_fields'][] = $entry + [
                'reason' => $this->getReadonlyReason($key, $resolved),
            ];
        }

        // Classify by requirement level
        if ($isRequired) {
            $result['required_fields'][] = $entry;
        } else {
            $level = $parentPath === ''
                ? ($requirementMap[$key] ?? 'OPTIONAL')
                : 'OPTIONAL';

            match ($level) {
                'REQUIRED'    => $result['required_fields'][] = $entry,
                'RECOMMENDED' => $result['recommended_fields'][] = $entry,
                default       => $result['optional_fields'][] = $entry,
            };
        }

        if ($enumValues !== null) {
            $result['enum_fields'][] = $entry + ['values' => $enumValues];
        }

        if ($pattern !== null) {
            $result['pattern_fields'][] = $entry + ['pattern' => $pattern];
        }

        // Recurse into children
        if (!empty($children)) {
            $this->recurseIntoChildren($resolved, $type, $path, $key, $requirementMap, $result);
        }
    }

    /**
     * Walk child properties of a group or array field recursively.
     */
    private function recurseIntoChildren(
        array $resolved,
        string $type,
        string $parentPath,
        string $parentKey,
        array $requirementMap,
        array &$result,
    ): void {
        if ($type === 'array' && isset($resolved['items']['properties'])) {
            $children = $resolved['items']['properties'];
            $childRequired = $resolved['items']['required'] ?? [];
        } else {
            $children = $resolved['properties'] ?? [];
            $childRequired = $resolved['required'] ?? [];
        }

        foreach ($children as $childKey => $childProperty) {
            if (!is_array($childProperty)) {
                continue;
            }

            $this->classifyField(
                key: $childKey,
                property: $childProperty,
                parentPath: $parentPath,
                parentKey: $parentKey,
                parentRequired: $childRequired,
                requirementMap: $requirementMap,
                result: $result,
            );
        }
    }

    /**
     * Extract child property key names from a resolved property schema.
     */
    private function resolveChildKeys(array $resolved, string $type): array
    {
        if (isset($resolved['properties']) && is_array($resolved['properties'])) {
            return array_keys($resolved['properties']);
        }

        if ($type === 'array' && isset($resolved['items']['properties']) && is_array($resolved['items']['properties'])) {
            return array_keys($resolved['items']['properties']);
        }

        return [];
    }

    // Requirement Level Detection

    /**
     * Build a map of root property name → requirement level.
     *
     * Sources (in priority order):
     *   1. Schema root "required" array
     *   2. propertyGroups[*].requirements
     *   3. allOf sections (non-conditional)
     */
    private function buildRequirementMap(
        array $propertyGroups,
        array $rootRequired,
        array $allOfSections,
    ): array {
        $map = [];

        foreach ($rootRequired as $field) {
            $map[$field] = 'REQUIRED';
        }

        foreach ($propertyGroups as $group) {
            $groupRequirement = strtoupper($group['requirements'] ?? 'OPTIONAL');
            $propertyNames = $group['propertyNames'] ?? [];

            foreach ($propertyNames as $name) {
                if (($map[$name] ?? null) === 'REQUIRED') {
                    continue;
                }
                $map[$name] = $groupRequirement;
            }
        }

        foreach ($allOfSections as $allOf) {
            if (!is_array($allOf) || isset($allOf['if'])) {
                continue;
            }
            if (isset($allOf['required']) && is_array($allOf['required'])) {
                foreach ($allOf['required'] as $field) {
                    if (!isset($map[$field]) || $map[$field] !== 'REQUIRED') {
                        $map[$field] = 'REQUIRED';
                    }
                }
            }
        }

        return $map;
    }

    // Hidden / Readonly Detection

    /**
     * Returns a string reason if the field should be hidden, or null if visible.
     * Checks the resolved schema first, then the original property.
     */
    private function getHiddenReason(string $key, array $property, array $resolved): ?string
    {
        if (in_array($key, self::INTERNAL_FIELDS, true)) {
            return 'Internal Amazon field — auto-populated by SP-API.';
        }

        if (in_array($key, self::COMPLIANCE_FIELDS, true)) {
            return 'Compliance / regulatory field — rarely relevant; hidden by convention.';
        }

        if (in_array($key, self::VARIATION_FIELDS, true)) {
            return 'Variation relationship field — only relevant for parent/child variant products.';
        }

        if (str_starts_with($key, 'compliance_')) {
            return 'Compliance field — hidden by convention.';
        }

        // Check resolved schema first (captures allOf-inherited metadata), then original
        $isVisible = $resolved['is_visible'] ?? $property['is_visible'] ?? null;
        if ($isVisible === false) {
            return 'Marked as not visible in the schema definition.';
        }

        return null;
    }

    /**
     * Returns a source-specific reason string for a readonly field.
     * Uses the resolved schema so allOf-inherited metadata is respected.
     */
    private function getReadonlyReason(string $key, array $resolved): string
    {
        if (in_array($key, self::AUTO_POPULATED_FIELDS, true)) {
            return match ($key) {
                'child_parent_sku_relationship' => 'Auto-set to parent SKU when product has a parent_id.',
                'merchant_shipping_group'       => 'Auto-set from shop default shipping template.',
                default                          => 'Auto-populated by the system.',
            };
        }

        if (isset($resolved['readOnly']) && $resolved['readOnly'] === true) {
            return 'Marked readOnly in schema.';
        }

        if (isset($resolved['editable']) && $resolved['editable'] === false) {
            return 'Marked non-editable by Amazon.';
        }

        if (isset($resolved['x-amazon-editable']) && $resolved['x-amazon-editable'] === false) {
            return 'x-amazon-editable is false.';
        }

        return 'Auto-populated by the system.';
    }

    /**
     * Detect whether a field is readonly via schema metadata.
     * Uses the resolved schema so allOf/anyOf/oneOf inherited flags are respected.
     *
     * Hidden fields are excluded (checked before this is called).
     */
    private function isReadonlyField(string $key, array $resolved): bool
    {
        if (in_array($key, self::AUTO_POPULATED_FIELDS, true)) {
            return true;
        }

        if (isset($resolved['readOnly']) && $resolved['readOnly'] === true) {
            return true;
        }

        if (isset($resolved['editable']) && $resolved['editable'] === false) {
            return true;
        }

        if (isset($resolved['x-amazon-editable']) && $resolved['x-amazon-editable'] === false) {
            return true;
        }

        return false;
    }

    // Schema Resolution Helpers

    /**
     * Simplify a property node by following allOf/anyOf/oneOf/items
     * to expose the effective type, enum, pattern, etc.
     *
     * Array wrappers are always preserved — only the items sub-schema is
     * recursively resolved so metadata like type, minItems, maxItems remains intact.
     */
    private function resolveEffectiveProperty(array $property): array
    {
        // allOf — merge all branches (array can nest inside allOf — still works)
        if (isset($property['allOf']) && is_array($property['allOf'])) {
            $merged = $property;
            unset($merged['allOf']);
            foreach ($property['allOf'] as $sub) {
                if (is_array($sub)) {
                    $merged = array_replace_recursive($merged, $this->resolveEffectiveProperty($sub));
                }
            }
            return $merged;
        }

        // anyOf — use first branch
        if (isset($property['anyOf'][0]) && is_array($property['anyOf'][0])) {
            return array_replace_recursive($property, $this->resolveEffectiveProperty($property['anyOf'][0]));
        }

        // oneOf — use first branch
        if (isset($property['oneOf'][0]) && is_array($property['oneOf'][0])) {
            return array_replace_recursive($property, $this->resolveEffectiveProperty($property['oneOf'][0]));
        }

        // items — preserve array wrapper, resolve items schema recursively
        // This handles: array→allOf, array→oneOf, array→anyOf
        if (isset($property['items']) && is_array($property['items'])) {
            $resolved = $property;
            $resolved['items'] = $this->resolveEffectiveProperty($property['items']);
            return $resolved;
        }

        return $property;
    }

    /**
     * Defensive $ref fallback — resolve local references from the cached root schema.
     */
    private function resolveLocalRef(array $resolved): array
    {
        if (!isset($resolved['$ref'])) {
            return $resolved;
        }

        $ref = $resolved['$ref'];
        unset($resolved['$ref']);

        if (!str_starts_with($ref, '#/')) {
            return $resolved;
        }

        // Traverse the cached root schema using the pointer path
        $parts = explode('/', substr($ref, 2));
        $current = $this->rootSchema;

        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (!isset($current[$part]) || !is_array($current[$part])) {
                return $resolved;
            }
            $current = $current[$part];
        }

        return array_replace_recursive($current, $resolved);
    }

    /**
     * Resolve the display title from the fully resolved schema.
     */
    private function resolveTitle(string $key, array $resolved): string
    {
        return $resolved['title']
            ?? $resolved['items']['title']
            ?? str_replace('_', ' ', ucfirst($key));
    }

    /**
     * Determine the UI field type from the resolved schema.
     */
    private function resolveFieldType(string $key, array $resolved): string
    {
        if ($this->isArrayType($resolved)) {
            return 'array';
        }

        if (str_contains(strtolower($key), 'image') || str_contains(strtolower($key), 'media')) {
            return 'image';
        }

        $format = $resolved['format'] ?? '';
        if ($format === 'uri' || $format === 'url' || $format === 'uri-reference') {
            return 'url';
        }

        $type = $resolved['type'] ?? 'string';

        if ($type === 'boolean') {
            return 'boolean';
        }

        if (in_array($type, ['number', 'integer'], true)) {
            return 'number';
        }

        if (isset($resolved['enum']) && is_array($resolved['enum'])) {
            return 'select';
        }

        if (($resolved['maxLength'] ?? 0) > 500) {
            return 'textarea';
        }

        if (isset($resolved['properties']) || $type === 'object') {
            return 'group';
        }

        return 'text';
    }

    /**
     * Detect whether the resolved schema represents an array.
     */
    private function isArrayType(array $resolved): bool
    {
        if (($resolved['type'] ?? null) === 'array') {
            return true;
        }

        if (isset($resolved['items']) && is_array($resolved['items'])) {
            return true;
        }

        return false;
    }

    /**
     * Extract enum values from the resolved schema.
     *
     * Supports: enum + enumNames/enumTitles, anyOf with enum,
     * anyOf with const (new), oneOf with const, and standalone const.
     */
    private function resolveEnum(array $resolved): ?array
    {
        // Direct enum
        if (isset($resolved['enum']) && is_array($resolved['enum']) && $resolved['enum'] !== []) {
            return $this->buildEnumValues($resolved['enum'], $resolved);
        }

        // anyOf branches with enum
        if (isset($resolved['anyOf']) && is_array($resolved['anyOf'])) {
            foreach ($resolved['anyOf'] as $branch) {
                if (is_array($branch) && isset($branch['enum']) && is_array($branch['enum']) && $branch['enum'] !== []) {
                    return $this->buildEnumValues($branch['enum'], $branch);
                }
            }

            // anyOf branches with const values
            $constValues = $this->extractBranchConsts($resolved['anyOf']);
            if ($constValues !== null) {
                return $constValues;
            }
        }

        // oneOf branches with const values
        if (isset($resolved['oneOf']) && is_array($resolved['oneOf'])) {
            $constValues = $this->extractBranchConsts($resolved['oneOf']);
            if ($constValues !== null) {
                return $constValues;
            }
        }

        // Standalone const
        if (array_key_exists('const', $resolved)) {
            $const = $resolved['const'];
            return [
                [
                    'value' => $const,
                    'label' => $resolved['title'] ?? (string) $const,
                ],
            ];
        }

        return null;
    }

    /**
     * Build the standard enum value+label array from raw enum values.
     */
    private function buildEnumValues(array $enum, array $context): array
    {
        $labels = $context['enumNames'] ?? $context['enumTitles'] ?? $enum;
        $values = [];

        foreach ($enum as $i => $value) {
            $values[] = [
                'value' => $value,
                'label' => isset($labels[$i]) ? (string) $labels[$i] : (string) $value,
            ];
        }

        return $values;
    }

    /**
     * Extract const values from anyOf/oneOf branches.
     * Returns null if not all branches use const (mixed mode).
     */
    private function extractBranchConsts(array $branches): ?array
    {
        $values = [];

        foreach ($branches as $branch) {
            if (!is_array($branch) || !array_key_exists('const', $branch)) {
                return null;
            }

            $const = $branch['const'];
            $values[] = [
                'value' => $const,
                'label' => $branch['title'] ?? (string) $const,
            ];
        }

        return $values;
    }

    /**
     * Extract pattern constraint from the resolved schema.
     */
    private function resolvePattern(array $resolved): ?string
    {
        if (isset($resolved['pattern']) && is_string($resolved['pattern'])) {
            return $resolved['pattern'];
        }

        if (isset($resolved['properties']['value']['pattern']) && is_string($resolved['properties']['value']['pattern'])) {
            return $resolved['properties']['value']['pattern'];
        }

        if (isset($resolved['items']['pattern']) && is_string($resolved['items']['pattern'])) {
            return $resolved['items']['pattern'];
        }

        if (isset($resolved['items']['properties']['value']['pattern']) && is_string($resolved['items']['properties']['value']['pattern'])) {
            return $resolved['items']['properties']['value']['pattern'];
        }

        return null;
    }

    /**
     * Return an empty classification result structure.
     */
    private function emptyResult(): array
    {
        return [
            'required_fields'    => [],
            'recommended_fields' => [],
            'optional_fields'    => [],
            'hidden_fields'      => [],
            'readonly_fields'    => [],
            'enum_fields'        => [],
            'pattern_fields'     => [],
        ];
    }
}
