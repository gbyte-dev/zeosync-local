<?php

namespace App\Services\Amazon;

use App\Services\Amazon\RefResolverService;

class SchemaRendererService
{
    protected array $schema;
    protected RefResolverService $resolver;
    public function __construct(array $schema)
    {
        $this->schema = $schema;
        $this->resolver =
            new RefResolverService($schema);
    }
    public function render(): array
    {
        $required =
            $this->schema['required']
            ?? [];
        $fields = [];
        foreach (
            ($this->schema['properties'] ?? [])
            as $name => $property
        ) {
            $fields[] = $this->buildField(
                $name,
                $property,
                $required
            );
        }
        return $fields;
    }

    protected function buildField(string $name, array $property,  array $required): array
    {
        return [
            'name' => $name,
            'title' => $property['title'] ?? ucfirst($name),
            'description' => $property['description']  ?? '',
            'required' => in_array($name, $required),
            'group' =>
            $this->detectGroup(
                $name
            ),
            'type' =>
            $this->detectType(
                $name,
                $property
            ),
            'multiple' =>
            $this->isMultiple(
                $property
            ),
            'options' =>
            $this->extractOptions(
                $property
            ),
            'default' =>
            $this->extractDefault(
                $property
            ),
            'children' =>
            $this->extractChildren(
                $property
            ),
            'selectors' =>
            $property['selectors']
                ?? []
        ];
    }
    protected function extractChildren(array $property): array
    {
        $children = [];
        $properties = $property['items']['properties'] ?? [];
        foreach ($properties as $name => $child) {
            $required = false;
            if (
                isset($property['items']['required']) &&
                is_array($property['items']['required'])
            ) {
                $required = in_array($name, $property['items']['required']);
            }
            $children[] = [
                'name' => $name,
                'title' =>
                $child['title']
                    ?? ucfirst(str_replace('_',  ' ', $name)),
                'description' => $child['description'] ?? '',
                'required' => $required,
                'type' =>  $this->detectType($name,  $child),
                'multiple' => $this->isMultiple($child),
                'options' =>  $this->extractOptions($child),
                'default' => $this->extractDefault($child),
                'children' => $this->extractChildren($child),
                'selectors' =>  $child['selectors'] ?? [],
            ];
        }
        return $children;
    }

    protected function detectType(string $name, array $property): string
    {
        if ($this->isImageField($name)) {
            return 'image';
        }
        $properties =  $property['items']['properties'] ?? [];
        /*
        battery
        package_dimensions
        etc
        */
        if (
            !empty($properties) && !isset($properties['value']) &&
            !isset($properties['name']) &&  !isset($properties['media_location'])
        ) {
            return 'group';
        }
        $value = $this->getMainProperty($property);
        if (($value['type'] ?? null)  === 'boolean') {
            return 'boolean';
        }
        if (!empty($this->extractOptions($property))) {
            return 'select';
        }
        if (($value['maxLength'] ?? 0) > 500) {
            return 'textarea';
        }
        return 'text';
    }

    protected function getMainProperty(array $property): array
    {
        $properties = $property['items']['properties'] ?? [];
        foreach (['value', 'name', 'media_location', 'type'] as $key) {
            if (isset($properties[$key])) {
                return $properties[$key];
            }
        }
        return [];
    }

    protected function isMultiple(array $property): bool
    {
        return ($property['maxUniqueItems'] ?? 1) > 1;
    }

    protected function extractOptions(array $property): array
    {
        $value = $value = $this->getPrimaryProperty($property);
        if (isset($value['enum'])) {
            $labels =  $value['enumNames'] ?? $value['enum'];
            return collect($value['enum'])
                ->map(function ($enum,  $index) use ($labels) {
                    return [
                        'value' => $enum,
                        'label' =>
                        $labels[$index]
                            ?? $enum
                    ];
                })
                ->values()
                ->toArray();
        }
        foreach (($value['anyOf'] ?? [])  as $anyOf) {
            if (!isset($anyOf['enum'])) {
                continue;
            }
            $labels = $anyOf['enumNames']  ?? $anyOf['enum'];
            return collect($anyOf['enum'])
                ->map(function (
                    $enum,
                    $index
                ) use ($labels) {
                    return [
                        'value' => $enum,
                        'label' =>
                        $labels[$index]
                            ?? $enum
                    ];
                })
                ->values()
                ->toArray();
        }
        return [];
    }
    protected function extractDefault(array $property)
    {
        return $property['default'] ?? null;
    }

    protected function isImageField(string $name): bool
    {
        return str_contains(strtolower($name), 'image');
    }

    protected function detectGroup(string $name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'image')) {
            return 'Images';
        }
        if (str_contains($name, 'variation')) {
            return 'Variations';
        }
        if (str_contains($name, 'parent')) {
            return 'Variations';
        }
        if (str_contains($name, 'description')) {
            return 'Content';
        }
        if (str_contains($name, 'bullet')) {
            return 'Content';
        }
        return 'General';
    }

    protected function getPrimaryProperty(array $property): array
    {
        $properties = $property['items']['properties']  ?? [];
        if (isset($properties['value'])) {
            return $properties['value'];
        }
        if (isset($properties['name'])) {
            return $properties['name'];
        }
        return [];
    }
}
