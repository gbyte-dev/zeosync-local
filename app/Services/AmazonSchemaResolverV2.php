<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Normalizes Amazon Product Type Definition (PTD) schemas by structurally 
 * resolving composition keywords (allOf, anyOf, oneOf), recursive nodes,
 * and internal JSON Pointer references ($ref).
 */
readonly class AmazonSchemaResolverV2
{
    /**
     * Normalizes the schema structure entirely, preparing it for downstream validation and transformation.
     *
     * @param array $schema The raw PTD schema.
     * @return array The fully normalized schema.
     * @throws RuntimeException If the schema structure is fundamentally broken or contains unresolvable references.
     */
    public function resolve(array $schema): array
    {
        Log::debug('Resolving schema root');

        $resolvedCache = [];
        $resolved = $this->resolveNode($schema, 'root', $schema, [], $resolvedCache);

        // Final assertion to guarantee no $ref nodes escaped normalization
        $this->assertNoRefs($resolved, 'root');

        Log::debug('Schema resolved');

        return $resolved;
    }

    /**
     * Recursively traverses and normalizes a given schema node.
     *
     * @param array $schema Current schema node being processed.
     * @param string $path Tracking path for debug logging.
     * @param array $rootSchema The original base schema required for $ref lookup.
     * @param array $visited Stack of visited $ref paths to prevent circular references.
     * @param array &$resolvedCache Shared cache of pure base $ref schemas.
     * @return array
     * @throws RuntimeException
     */
    private function resolveNode(
        array $schema,
        string $path,
        array $rootSchema,
        array $visited,
        array &$resolvedCache
    ): array {

        // 1. Resolve $ref nodes and return early to prevent double traversal
        if (array_key_exists('$ref', $schema)) {
            $refPath = $schema['$ref'];
            unset($schema['$ref']);

            if (in_array($refPath, $visited, true)) {
                throw new RuntimeException("Circular reference detected at path: {$path} -> {$refPath}");
            }

            // Cache ONLY the pure base definition, devoid of local overrides
            if (!isset($resolvedCache[$refPath])) {
                $newVisited = $visited;
                $newVisited[] = $refPath;

                Log::debug("Resolving \$ref at path: {$path} -> {$refPath}");

                $refSchema = $this->resolvePointer($rootSchema, $refPath);
                $resolvedCache[$refPath] = $this->resolveNode($refSchema, "{$path}->{$refPath}", $rootSchema, $newVisited, $resolvedCache);
            }

            $resolvedRef = $resolvedCache[$refPath];

            // Resolve any remaining local overrides (e.g. allOf, properties) on this specific node
            $resolvedLocal = $this->resolveNode($schema, "{$path}.local", $rootSchema, $visited, $resolvedCache);

            // Merge local overrides ON TOP OF the base referenced schema and return immediately
            return $this->mergeSchemas($resolvedRef, $resolvedLocal);
        }

        // 2. Resolve items BEFORE composition to avoid traversing merged elements multiple times
        if (array_key_exists('items', $schema)) {
            if (!is_array($schema['items'])) {
                throw new RuntimeException("Invalid schema structure: items must be an array at path {$path}.");
            }

            Log::debug("Resolving items at path: {$path}.items");
            $schema['items'] = $this->resolveNode($schema['items'], "{$path}.items", $rootSchema, $visited, $resolvedCache);
        }

        // 3. Resolve properties BEFORE composition
        if (array_key_exists('properties', $schema)) {
            if (!is_array($schema['properties'])) {
                throw new RuntimeException("Invalid schema structure: properties must be an array at path {$path}.");
            }

            foreach ($schema['properties'] as $key => $propertySchema) {
                if (is_array($propertySchema)) {
                    $schema['properties'][$key] = $this->resolveNode($propertySchema, "{$path}.properties.{$key}", $rootSchema, $visited, $resolvedCache);
                }
            }
        }

        // 4. Resolve and merge composition keywords
        if (array_key_exists('allOf', $schema)) {
            if (!is_array($schema['allOf'])) {
                throw new RuntimeException("Invalid schema structure: allOf must be an array at path {$path}.");
            }

            Log::debug("Resolving allOf at path: {$path}");
            $allOf = $schema['allOf'];
            unset($schema['allOf']);

            foreach ($allOf as $index => $subSchema) {
                if (!is_array($subSchema)) {
                    throw new RuntimeException("Invalid schema structure: allOf elements must be arrays at path {$path}.");
                }

                $resolvedSubSchema = $this->resolveNode($subSchema, "{$path}.allOf[{$index}]", $rootSchema, $visited, $resolvedCache);
                $schema = $this->mergeSchemas($schema, $resolvedSubSchema);
            }
        }

        if (array_key_exists('anyOf', $schema)) {
            if (!is_array($schema['anyOf'])) {
                throw new RuntimeException("Invalid schema structure: anyOf must be an array at path {$path}.");
            }

            Log::debug("Resolving anyOf at path: {$path}");
            $anyOf = $schema['anyOf'];
            unset($schema['anyOf']);

            if (isset($anyOf[0]) && is_array($anyOf[0])) {
                $resolvedSubSchema = $this->resolveNode($anyOf[0], "{$path}.anyOf[0]", $rootSchema, $visited, $resolvedCache);
                $schema = $this->mergeSchemas($schema, $resolvedSubSchema);
            }
        }

        if (array_key_exists('oneOf', $schema)) {
            if (!is_array($schema['oneOf'])) {
                throw new RuntimeException("Invalid schema structure: oneOf must be an array at path {$path}.");
            }

            Log::debug("Resolving oneOf at path: {$path}");
            $oneOf = $schema['oneOf'];
            unset($schema['oneOf']);

            if (isset($oneOf[0]) && is_array($oneOf[0])) {
                $resolvedSubSchema = $this->resolveNode($oneOf[0], "{$path}.oneOf[0]", $rootSchema, $visited, $resolvedCache);
                $schema = $this->mergeSchemas($schema, $resolvedSubSchema);
            }
        }

        return $schema;
    }

    /**
     * Resolves a JSON pointer against the root schema.
     *
     * @param array $root The original base schema structure.
     * @param string $pointer The JSON pointer (e.g., "#/$defs/MarketplaceId").
     * @return array
     * @throws RuntimeException
     */
    private function resolvePointer(array $root, string $pointer): array
    {
        if (!str_starts_with($pointer, '#/')) {
            throw new RuntimeException("Unsupported \$ref format: {$pointer}. Only local JSON pointers starting with '#/' are supported.");
        }

        // Strip '#/' and split by '/'
        $parts = explode('/', substr($pointer, 2));
        $current = $root;

        foreach ($parts as $part) {
            // Unescape standard JSON pointer characters
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (!isset($current[$part])) {
                throw new RuntimeException("Unresolvable \$ref pointer: {$pointer} at segment '{$part}'.");
            }

            $current = $current[$part];
        }

        if (!is_array($current)) {
            throw new RuntimeException("Resolved \$ref {$pointer} is not a valid schema array.");
        }

        return $current;
    }

    /**
     * Merges extended definitions into a base schema node.
     * Uses array_replace_recursive to prevent scalar elements from becoming arrays, 
     * while specifically preserving zero-indexed list dependencies like required fields and enums.
     *
     * @param array $base
     * @param array $extension
     * @return array
     */
    private function mergeSchemas(array $base, array $extension): array
    {
        $merged = array_replace_recursive($base, $extension);

        $this->mergeUniqueListProperty($merged, $base, $extension, 'required');
        $this->mergeUniqueListProperty($merged, $base, $extension, 'enum');

        return $merged;
    }

    /**
     * Helper to safely merge list-based schema keywords without key collision,
     * maintaining unique values across the resulting array list.
     *
     * @param array &$merged The reference to the merged array being built.
     * @param array $base The original base schema structure.
     * @param array $extension The schema extension being layered on top.
     * @param string $property The specific list-based key to merge (e.g., 'required', 'enum').
     */
    private function mergeUniqueListProperty(array &$merged, array $base, array $extension, string $property): void
    {
        if (
            isset($base[$property], $extension[$property]) &&
            is_array($base[$property]) &&
            is_array($extension[$property])
        ) {
            $merged[$property] = array_values(array_unique(array_merge(
                $base[$property],
                $extension[$property]
            )));
        }
    }

    /**
     * Recursively asserts that no $ref keys exist in the finalized schema.
     *
     * @param array $schema
     * @param string $path
     * @throws RuntimeException
     */
    private function assertNoRefs(array $schema, string $path): void
    {
        if (array_key_exists('$ref', $schema)) {
            throw new RuntimeException("Unresolved \$ref found after normalization at path: {$path}");
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $this->assertNoRefs($value, "{$path}.{$key}");
            }
        }
    }
}
