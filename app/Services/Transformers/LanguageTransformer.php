<?php

declare(strict_types=1);

namespace App\Services\Transformers;
use Illuminate\Support\Facades\Log;
use App\DTOs\AmazonTransformerConfig;

class LanguageTransformer extends AbstractPayloadTransformer
{
    /**
     * Determine whether the schema represents a localized string.
     */
    public function matches(array $schema): bool
    {
        $properties = $schema['properties']
            ?? ($schema['items']['properties'] ?? []);

        return isset($properties['language_tag']);
    }

    /**
     * Transform into Amazon localized string structure.
     */
    public function transform(
        array $schema,
        mixed $value,
        AmazonTransformerConfig $config
    ): array {

        Log::info('LANGUAGE START', [
            'input' => $value,
        ]);

        return array_map(function ($item) use (
            $schema,
            $config
        ) {

            $text = is_array($item)
                ? ($item['value'] ?? '')
                : $item;

            $language = (
                is_array($item)
                && !empty($item['language_tag'])
            )
                ? $item['language_tag']
                : $config->languageTag;

            $result = [
                'value' => (string) $text,
                'language_tag' => $language,
            ];
            Log::info('LANGUAGE RESULT', [
                'result' => $result,
            ]);

            return $this->injectDefaults(
                $result,
                $schema,
                $config
            );
        }, $this->normalizeArray($value));
    }
}
