<?php

namespace App\Services\Amazon;

class RefResolverService
{
    protected array $schema;

    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    public function resolve(string $ref): ?array
    {
        if (!str_starts_with($ref, '#/')) {
            return null;
        }

        $path = str_replace('#/', '', $ref);

        $segments = explode('/', $path);

        $value = $this->schema;

        foreach ($segments as $segment) {

            if (!isset($value[$segment])) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}