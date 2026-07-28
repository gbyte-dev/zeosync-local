<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\TraversalContext;
use Illuminate\Support\Facades\Log;

readonly class TransformerResolver
{
    public function __construct(
        private MoneyTransformer $moneyTransformer,
        private DimensionsTransformer $dimensionsTransformer,
        private MeasurementTransformer $measurementTransformer,
        private InventoryTransformer $inventoryTransformer,
        private LanguageTransformer $languageTransformer,
        private GenericObjectTransformer $genericObjectTransformer,
        private GenericTransformer $genericTransformer
    ) {
        $this->genericObjectTransformer->setResolver($this);
    }

    /**
     * Resolves the appropriate leaf transformer based on the schema structure and traversal context.
     *
     * @param array $schema The structural schema definition from Amazon SP-API.
     * @param TraversalContext $context The structural path context of the current field.
     * @return PayloadTransformerInterface The matched transformer.
     */
    public function resolveLeafTransformer(array $schema, TraversalContext $context): PayloadTransformerInterface
    {
        Log::info('RESOLVER CHECK WITH CONTEXT', [
            'type' => $schema['type'] ?? null,
            'path' => $context->path,
            'field' => $context->field,
            'parentField' => $context->parentField,
            'depth' => $context->depth,
            'has_properties' => isset($schema['properties']),
            'has_item_properties' => isset($schema['items']['properties']),
            'keys' => array_keys($schema),
        ]);

        $unwrappedSchema = $this->unwrapSchema($schema);

        /*
        |--------------------------------------------------------------------------
        | Context-Aware Resolution Strategy
        |--------------------------------------------------------------------------
        | TraversalContext (path, field, parentField, depth) is retained here to
        | support context-aware structural disambiguation in future phases.
        | 
        | While transformers currently rely strictly on schema signature matching, 
        | this context ensures that structurally identical schemas at different 
        | depths or nested paths can be safely routed to specialized transformers 
        | without resorting to hardcoded field-name routing or payload hacks.
        */

        if ($this->moneyTransformer->matches($unwrappedSchema)) {
            return $this->moneyTransformer;
        }

        if ($this->dimensionsTransformer->matches($unwrappedSchema)) {
            return $this->dimensionsTransformer;
        }

        if ($this->measurementTransformer->matches($unwrappedSchema)) {
            return $this->measurementTransformer;
        }

        if ($this->inventoryTransformer->matches($unwrappedSchema)) {
            return $this->inventoryTransformer;
        }

        if ($this->languageTransformer->matches($unwrappedSchema)) {
            return $this->languageTransformer;
        }

        if ($this->genericObjectTransformer->matches($unwrappedSchema)) {
            return $this->genericObjectTransformer;
        }

        return $this->genericTransformer;
    }

    /**
     * Resolves the appropriate transformer strictly based on the schema structure.
     * Maintained for backward compatibility or cases where TraversalContext is not yet available.
     *
     * @param array $schema The structural schema definition from Amazon SP-API.
     * @return PayloadTransformerInterface The matched transformer.
     */
    public function resolve(array $schema): PayloadTransformerInterface
    {
        return $this->resolveLeafTransformer($schema, new TraversalContext());
    }

    /**
     * Recursively unwraps composite schemas (allOf, anyOf, oneOf, items) to expose root properties.
     *
     * @param array $schema
     * @return array
     */
    private function unwrapSchema(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return $schema;
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $subSchema) {
                // Using array_replace_recursive prevents scalar values (like 'type' => 'string') 
                // from being converted into arrays, which happens with array_merge_recursive.
                $schema = array_replace_recursive($schema, $this->unwrapSchema($subSchema));
            }
            unset($schema['allOf']);
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'][0] ?? null)) {
            $schema = array_replace_recursive($schema, $this->unwrapSchema($schema['anyOf'][0]));
            unset($schema['anyOf']);
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf'][0] ?? null)) {
            $schema = array_replace_recursive($schema, $this->unwrapSchema($schema['oneOf'][0]));
            unset($schema['oneOf']);
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->unwrapSchema($schema['items']);
        }

        return $schema;
    }
}
