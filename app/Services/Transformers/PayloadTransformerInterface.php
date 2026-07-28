<?php

declare(strict_types=1);

namespace App\Services\Transformers;

use App\DTOs\AmazonTransformerConfig;

/**
 * Defines the contract for all Amazon SP-API payload transformers.
 *
 * Transformers are responsible for structuring raw payload values into
 * compliant formats based strictly on Amazon Product Type Definition (PTD) schemas.
 */
interface PayloadTransformerInterface
{
    /**
     * Determines whether this transformer can process the given schema structure.
     *
     * The evaluation must rely purely on the schema's structural definition
     * (e.g., specific property keys or types) and NEVER on hardcoded field names,
     * payload values, or arbitrary business logic.
     *
     * @param array $schema The structural schema definition from Amazon SP-API.
     * @return bool True if the transformer supports the schema structure, false otherwise.
     */
    public function matches(array $schema): bool;

    /**
     * Transforms the raw user payload into an Amazon SP-API compliant structure.
     *
     * @param array $schema The structural schema definition for the specific field.
     * @param mixed $value The raw payload value to be transformed.
     * @param AmazonTransformerConfig $config Configuration DTO containing defaults (e.g., marketplace ID, currency).
     * @return array The compliant array structure ready for the Amazon SP-API payload.
     */
    public function transform(array $schema, mixed $value, AmazonTransformerConfig $config): array;
}
