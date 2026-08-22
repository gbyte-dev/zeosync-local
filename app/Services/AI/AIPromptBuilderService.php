<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIPromptBuilderService
{
    /**
     * Builds the System and User prompts for the AI Listing Generator.
     *
     * @param array $shopifyProduct
     * @param array $schemaClassification
     * @param array $currentValues
     * @param string $category
     * @return array{system_prompt: string, user_prompt: string}
     */
    public function buildPrompt(
        array $shopifyProduct,
        array $schemaClassification,
        array $currentValues = [],
        string $category = ''
    ): array {
        $metrics = [
            'required_count'   => 0,
            'optional_count'   => 0,
            'enum_count'       => 0,
            'large_enum_count' => 0,
        ];

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt   = $this->buildUserPrompt(
            $shopifyProduct,
            $schemaClassification,
            $currentValues,
            $category,
            $metrics
        );

        Log::info('AIPromptBuilderService Optimization Metrics', [
            'system_prompt_length' => strlen($systemPrompt),
            'user_prompt_length'   => strlen($userPrompt),
            'required_count'       => $metrics['required_count'],
            'optional_count'       => $metrics['optional_count'],
            'enum_count'           => $metrics['enum_count'],
            'large_enum_count'     => $metrics['large_enum_count'],
        ]);

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userPrompt,
        ];
    }


    /**
     * Defines the strict, token-optimized behavior parameters for the LLM.
     */
    private function buildSystemPrompt(): string
    {
        return "Amazon Listing AI. RULES: 1. Output ONLY valid JSON. No markdown. 2. Use ONLY keys from the 'out' template. 3. Respect schema constraints. 4. Generate missing values only. 5. Be concise.";
    }

    /**
     * Assembles the structured, highly compacted JSON prompt.
     */
    private function buildUserPrompt(
        array $shopifyProduct,
        array $classification,
        array $currentValues,
        string $category,
        array &$metrics
    ): string {
        $cleanValues = $this->removeEmptyValues($currentValues);
        $filledKeys  = array_keys($cleanValues);

        $schemaContext = $this->buildSchemaContext($classification, $filledKeys, $metrics);

        $promptData = $this->removeEmptyValues([
            'task' => 'Gen missing fields',
            'cat'  => $category,
            'prod' => $this->buildProductContext($shopifyProduct),
            'fill' => $cleanValues,
            'sch'  => $schemaContext,
        ]);

        // Attach template after cleanup to ensure empty expected strings are not purged
        $promptData['out'] = $this->buildExpectedJson($schemaContext);

        return json_encode($promptData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function buildGenericPrompt(
        string $productName,
        ?string $productDescription,
        string $category
    ): array {

        $fieldHints = [
            'item_weight' => 'e.g. 250 grams',
            'item_package_weight' => 'e.g. 300 grams',
            'item_package_dimensions' => '10L*20W*30H centimeters. L=Length, W=Width, H=Height, T=Thickness, D=Depth.',
            'item_dimensions' => '39L x 17.5W x 3H Centimeters. L=Length, W=Width, H=Height.',
        ];

        $hintText = '';

        foreach ($fieldHints as $field => $hint) {
            $hintText .= "{$field}: {$hint}\n";
        }

        $systemPrompt = <<<PROMPT
Amazon listing autofill engine.

Return ONLY valid JSON.

Generate values for ALL of these fields:

title_differentiation
brand
model_number
product_description
bullet_point
generic_keyword
number_of_items
item_package_quantity
part_number
model_name
manufacturer
color
size
pattern
item_weight
item_package_weight
condition_note
max_order_quantity
unit_count
warranty_description
item_package_dimensions
item_dimensions

FORMAT RULES:
{$hintText}

Rules:
- Do NOT generate item_name.
- Product Name is mandatory and is the main product signal.
- Product Description is optional.
- Use Product Category as the strongest category signal.
- Generate a useful value for every requested field.
- Use reasonable best-effort inference when product details are missing.
- After the required fields above, generate additional useful category-relevant fields when appropriate.
- Generate as many useful fields as reasonably possible.
- Use snake_case field names.
- Do not duplicate fields.
- bullet_point must be an array of 5 concise points.
- generic_keyword must be a concise comma-separated string.
- unit_count must use the format "<number> Count" with a number from 1 to 50.
- Do not return null, empty strings, unknown, N/A, or placeholders.
- Do not generate ASIN, SKU, GTIN, UPC, EAN, MPN, external IDs, price, inventory, shipping, marketplace IDs, or seller information.
- Return JSON only.
PROMPT;

        $userPrompt = "Category: {$category}\nProduct Name: {$productName}";

        if (!empty($productDescription)) {
            $userPrompt .= "\nProduct Description: {$productDescription}";
        }

        $userPrompt .= "\nGenerate all requested fields, then add additional relevant fields.\nReturn JSON only.";

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userPrompt,
        ];
    }
    public function buildErrorAutoFillPrompt(
        string $productName,
        ?string $productDescription,
        string $category,
        array $errors
    ): array {
        $errorLines = [];

        foreach ($errors as $error) {
            $field = $error['attributeNames'][0] ?? null;
            $message = $error['message'] ?? null;

            if ($field && $message) {
                $errorLines[] = "{$field}: {$message}";
            }
        }

        $systemPrompt = <<<'PROMPT'
You are an Amazon listing autofill engine.

Generate usable values for the requested missing Amazon fields using the product title, category, description, and validation errors.

RULES:
- Return JSON only.
- Use exactly the provided field names; never rename, add, or remove fields.
- Fill as many requested fields as reasonably possible using strong product/category inference.
- Match values to the correct field meaning; never confuse attributes such as color, pattern, material, size, quantity, dimensions, or weight.
- Use reasonable best-effort assumptions when exact values are unavailable.
- Never return null, empty, placeholder, or unknown values.
- Never fabricate ASINs, external IDs, or other unique identifiers.
- Generate category-appropriate values with the required datatype, format, and units.
- Return only requested fields with usable values.
PROMPT;

        $userPrompt = <<<PROMPT
PRODUCT:
Title: {$productName}
Category: {$category}
Description: {$productDescription}

AMAZON VALIDATION ERRORS:
PROMPT;

        $userPrompt .= "\n- " . implode("\n- ", $errorLines);
        $userPrompt .= "\n\nReturn ONLY JSON.";

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
        ];
    }


    public function buildSingleFieldPrompt(
        string $productName,
        string $category,
        string $field,
        ?string $fieldDescription = null,
        ?string $fieldHint = null
    ): array {

        $productName = \Illuminate\Support\Str::limit(trim($productName), 60, '');

        $systemPrompt = <<<PROMPT
You are an Amazon Listing Expert.

Return ONLY a valid JSON object.

Rules:
- Do not return markdown.
- Do not return explanations.
- Do not return plain text.
- The JSON must contain ONLY one key named "{$field}".
- Never return "Not Applicable" or any equivalent.
- Always return a real, useful value for the requested field.
- Use the product information to determine the value.
PROMPT;
        $hintSection = '';

        if (!empty($fieldHint)) {
            $hintSection = <<<TEXT

Expected Format:
{$fieldHint}

Strictly follow this format when generating the value.
TEXT;
        }

        $userPrompt = <<<PROMPT
Category: {$category}

Product: {$productName}

Field: {$field}

Field Description:
{$fieldDescription}
{$hintSection}

Generate only the "{$field}" field.

Example output:

{
  "{$field}": "generated value"
}
PROMPT;

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userPrompt,
        ];
    }
    /**
     * Normalizes and severely truncates the Shopify product to conserve tokens.
     */
    private function buildProductContext(array $product): array
    {
        $desc = isset($product['body']) ? strip_tags($product['body']) : null;
        if (is_string($desc) && strlen($desc) > 800) {
            $desc = substr($desc, 0, 797) . '...';
        }

        $normalized = [
            'title'  => $product['title'] ?? null,
            'desc'   => $desc,
            'vendor' => $product['vendor'] ?? null,
            'type'   => $product['product_type'] ?? null,
            'tags'   => $product['tags'] ?? null,
            'imgs'   => array_slice($this->normalizeImages($product['images'] ?? []), 0, 2),
            'vars'   => array_slice($this->normalizeVariants($product['variants'] ?? []), 0, 2),
        ];

        return $this->removeEmptyValues($normalized);
    }

    /**
     * Extracts only raw image URLs.
     */
    private function normalizeImages(array $images): array
    {
        return array_values(array_filter(array_map(fn($img) => $img['src'] ?? null, $images)));
    }

    /**
     * Extracts functional variant data, shedding inventory metadata and IDs.
     */
    private function normalizeVariants(array $variants): array
    {
        return array_map(function ($v) {
            $options = array_filter([$v['option1'] ?? null, $v['option2'] ?? null, $v['option3'] ?? null]);
            return $this->removeEmptyValues([
                'title' => $v['title'] ?? null,
                'price' => $v['price'] ?? null,
                'opts'  => implode('/', $options) ?: null
            ]);
        }, $variants);
    }

    /**
     * Recursively purges nulls, empty strings, and empty arrays from any payload.
     */
    private function removeEmptyValues(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = $this->removeEmptyValues($value);
                if (empty($value)) continue;
            } elseif ($value === null || $value === '') {
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Compiles schema constraints into a dense, flat hierarchy mapping strictly to top-level keys.
     */
    private function buildSchemaContext(
        array $classification,
        array $filledKeys,
        array &$metrics
    ): array {
        $enums = [];

        foreach ($classification['enum_fields'] ?? [] as $f) {
            $topKey = explode('.', $f['path'] ?? $f['key'])[0];
            $enums[$topKey] = $f['values'] ?? [];
        }

        $patterns = [];

        foreach ($classification['pattern_fields'] ?? [] as $f) {
            $topKey = explode('.', $f['path'] ?? $f['key'])[0];
            $patterns[$topKey] = $f['pattern'] ?? '';
        }

        $schema = [];
        $seenKeys = [];

        $req = $this->extractFields(
            $classification['required_fields'] ?? [],
            $filledKeys,
            $enums,
            $patterns,
            true,
            $seenKeys,
            $metrics
        );

        if ($req) {
            $schema['req'] = $req;
        }

        $rec = $this->extractFields(
            $classification['recommended_fields'] ?? [],
            $filledKeys,
            $enums,
            $patterns,
            true,
            $seenKeys,
            $metrics
        );

        if ($rec) {
            $schema['rec'] = $rec;
        }

        $opt = $this->extractFields(
            $classification['optional_fields'] ?? [],
            $filledKeys,
            $enums,
            $patterns,
            false,
            $seenKeys,
            $metrics
        );

        if ($opt) {
            $schema['opt'] = $opt;
        }

        return $schema;
    }
    /**
     * Extracts missing field constraints without duplicating structure.
     */
    private function extractFields(
        array $fields,
        array $filledKeys,
        array $enums,
        array $patterns,
        bool $isPriority,
        array &$seenKeys,
        array &$metrics
    ): array {
        $result = [];

        foreach ($fields as $field) {
            $key  = $field['key'] ?? null;
            $path = $field['path'] ?? $key;

            // Skip nested properties (e.g. item_name.value) to keep JSON perfectly flat
            if ($key !== $path) {
                continue;
            }

            if (!$key || ($field['is_hidden'] ?? false) || ($field['is_readonly'] ?? false) || in_array($key, $filledKeys, true) || isset($seenKeys[$key])) {
                continue;
            }

            $type = strtolower($field['type'] ?? 'string');
            if (in_array($type, ['measurement', 'dimensions', 'currency', 'group', 'object', 'array'], true)) {
                $type = 'string';
            }

            $hasEnum    = !empty($enums[$key]);
            $hasPattern = !empty($patterns[$key]) && $type === 'string';

            $descStr = '';
            if (!$hasEnum && !empty($field['definition']['description'])) {
                $descStr = preg_replace('/\s+/', ' ', trim($field['definition']['description']));
                if (strlen($descStr) > 50) {
                    $descStr = substr($descStr, 0, 47) . '...';
                }
            }

            // Skip trivial optional fields
            if (!$isPriority && !$hasEnum && !$hasPattern && empty($descStr)) {
                continue;
            }

            $seenKeys[$key] = true;

            if ($isPriority) {
                $metrics['required_count']++;
            } else {
                $metrics['optional_count']++;
            }

            $def = ['type' => $type];

            if ($descStr) {
                $def['desc'] = $descStr;
            }

            if ($hasEnum) {
                $metrics['enum_count']++;
                $enumValues = array_values(array_unique(array_filter(array_map(fn($e) => $e['value'] ?? null, $enums[$key]))));

                if (count($enumValues) > 20) {
                    $def['enum'] = 'large_enum';
                    $metrics['large_enum_count']++;
                } else {
                    $def['enum'] = $enumValues;
                }
            }

            if ($hasPattern) {
                $def['pattern'] = $patterns[$key];
            }

            // Ultimate compactness: If it's a required string with zero constraints, emit ONLY type
            $result[$key] = (count($def) === 1 && $type === 'string') ? ['type' => 'string'] : $def;
        }

        return $result;
    }

    /**
     * Generates a rigid JSON template populated entirely with default values.
     */
    private function buildExpectedJson(array $schemaContext): array
    {
        $template = [];

        foreach (['req', 'rec', 'opt'] as $group) {
            if (!isset($schemaContext[$group])) continue;

            foreach ($schemaContext[$group] as $key => $def) {
                $type = $def['type'] ?? 'string';

                $template[$key] = match ($type) {
                    'number', 'integer', 'int', 'float' => 0,
                    'boolean' => false,
                    default => "",
                };
            }
        }

        return $template;
    }
}
