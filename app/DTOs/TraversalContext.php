<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TraversalContext
{
    public function __construct(
        public string $path = '',
        public ?string $field = null,
        public ?string $parentField = null,
        public int $depth = 0,
    ) {}

    public function child(string $field): self
    {
        return new self(
            path: $this->path === ''
                ? $field
                : "{$this->path}.{$field}",

            field: $field,

            parentField: $this->field,

            depth: $this->depth + 1,
        );
    }
}