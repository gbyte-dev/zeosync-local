<?php 

namespace App\Services\Amazon;

class SchemaParser
{
    protected array $schema;

    public function parse(array $schema): array
    {
        $this->schema = $schema;

        $fields = [];

        $required = $schema['required'] ?? [];

        foreach (($schema['properties'] ?? []) as $name => $property) {

            $fields[] = [
                'name' => $name,
                'title' => $property['title'] ?? $name,
                'description' => $property['description'] ?? '',
                'required' => in_array($name, $required),
                'type' => $this->detectFieldType($property),
                'enum' => $this->extractEnum($property),
                'multiple' => $this->isMultiple($property),
                'group' => $this->detectGroup($name),
            ];
        }

        return $fields;
    }

    protected function detectFieldType(array $property): string
    {
        if ($this->isImageField($property)) {
            return 'image';
        }

        $value = $property['items']['properties']['value'] ?? [];

        if (($value['type'] ?? '') === 'boolean') {
            return 'boolean';
        }

        if (isset($value['enum'])) {
            return 'select';
        }

        if (($value['maxLength'] ?? 0) > 500) {
            return 'textarea';
        }

        return 'text';
    }

    protected function extractEnum(array $property): array
    {
        return $property['items']['properties']['value']['enum'] ?? [];
    }

    protected function isMultiple(array $property): bool
    {
        return ($property['maxUniqueItems'] ?? 1) > 1;
    }

    protected function isImageField(array $property): bool
    {
        return str_contains(
            strtolower($property['title'] ?? ''),
            'image'
        );
    }

    protected function detectGroup(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);

        if (str_contains($fieldName, 'image')) {
            return 'Images';
        }

        if (
            str_contains($fieldName, 'variation') ||
            str_contains($fieldName, 'parent')
        ) {
            return 'Variations';
        }

        if (
            str_contains($fieldName, 'description') ||
            str_contains($fieldName, 'bullet')
        ) {
            return 'Content';
        }

        return 'General';
    }
}