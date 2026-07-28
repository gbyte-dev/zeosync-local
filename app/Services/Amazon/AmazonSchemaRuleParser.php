<?php

namespace App\Services\Amazon;

class AmazonSchemaRuleParser
{
    public function extract(array $schema): array
    {
        $root = $schema['real_schema'] ?? $schema;
        $rules = [];
        $seen = [];
        $this->scan($root, [], $rules, $seen);
        return $rules;
    }
    private function scan(
        array $node,
        array $path,
        array &$rules,
        array &$seen
    ): void {
        if (isset($node['if']) && (isset($node['then']) || isset($node['else']))) {
            $rule = [
                'path' => implode('.', $path),
                'if'   => $node['if'],
                'then' => $node['then'] ?? null,
                'else' => $node['else'] ?? null,
            ];

            $this->recursiveKsort($rule);

            $hash = hash(
                'sha256',
                json_encode(
                    $rule,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                )
            );

            if (!isset($seen[$hash])) {

                $seen[$hash] = true;

                $rules[] = $rule;
            }
        }
        foreach (['allOf', 'anyOf', 'oneOf'] as $group) {
            if (!empty($node[$group]) && is_array($node[$group])) {
                foreach ($node[$group] as $child) {
                    if (is_array($child)) {
                        $this->scan($child, $path, $rules, $seen);
                    }
                }
            }
        }
        if (!empty($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $key => $child) {
                if (is_array($child)) {
                    $this->scan($child, array_merge($path, [$key]), $rules, $seen);
                }
            }
        }
        if (!empty($node['items']) && is_array($node['items'])) {
            $this->scan($node['items'], $path, $rules, $seen);
        }
    }
    private function recursiveKsort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }

        ksort($array);
    }
}
