<?php
namespace App\Services;
use ReflectionClass;
class AmazonPayloadAnalyzer
{
    public function getPayloadFields(
        object $service,
        string $method,
        array $args = []
    ): array {
        if (!method_exists($service, $method)) {
            return [];
        }
        $reflection =
            new ReflectionClass($service);
        $payloadMethod =
            $reflection->getMethod($method);
        $payloadMethod->setAccessible(true);
        $payload =
            $payloadMethod->invokeArgs(
                $service,
                $args
            );
        if (!is_array($payload)) {
            return [];
        }
        return $this->extractPayloadStructure(
            $payload
        );
    }
    private array $skipFields = [
        'marketplace_id',
        'language_tag',
        'size_system',
        'size_class',
        'fulfillment_channel_code'
    ];
    public function getPayloadMethodBySlug(
        string $slug,
        bool $isVariant = false
    ): ?string {
        $slug = strtolower(trim($slug));
        return match ($slug) {
            'shirt',
            't-shirt',
            'shorts'
            => $isVariant
                ? 'shirtVariantPayload'
                : 'shirtFullPayload',
            'phone',
            'mobile'
            => $isVariant
                ? 'phoneVariantPayload'
                : 'phoneFullPayload',
            'headphones'
            => $isVariant
                ? 'headphonesVariantPayload'
                : 'headphonesFullPayload',
            'pants',
            'jeans'
            => $isVariant
                ? 'pantsVariantPayload'
                : 'pantsFullPayload',
            'shoes'
            => $isVariant
                ? 'shoesVariantPayload'
                : 'shoesFullPayload',
            'backpack'
            => $isVariant
                ? 'backpackVariantPayload'
                : 'backpackFullPayload',
            'input_mouse'
            => $isVariant
                ? 'inputMouseVariantPayload'
                : 'inputMouseFullPayload',
            'watch'
            => $isVariant
                ? 'watchVariantPayload'
                : 'watchFullPayload',
            'camera'
            => $isVariant
                ? 'cameraVariantPayload'
                : 'cameraFullPayload',
            'monitor'
            => $isVariant
                ? 'monitorVariantPayload'
                : 'monitorFullPayload',
            'notebook_computer'
            => $isVariant
                ? 'notebookComputerVariantPayload'
                : 'notebookComputerFullPayload',
            'footwear'
            => $isVariant
                ? 'footwearVariantPayload'
                : 'footwearFullPayload',
            'handbag'
            => $isVariant
                ? 'handbagVariantPayload'
                : 'handbagFullPayload',
            'cable'
            => $isVariant
                ? 'cableVariantPayload'
                : 'cableFullPayload',
            'chair'
            => $isVariant
                ? 'chairVariantPayload'
                : 'chairFullPayload',
            'table'
            => $isVariant
                ? 'tableVariantPayload'
                : 'tableFullPayload',
            'sofa'
            => $isVariant
                ? 'sofaVariantPayload'
                : 'sofaFullPayload',
            'mattress'
            => $isVariant
                ? 'mattressVariantPayload'
                : 'mattressFullPayload',
            'bed'
            => $isVariant
                ? 'bedVariantPayload'
                : 'bedFullPayload',
            'stapler'
            => $isVariant
                ? 'staplerVariantPayload'
                : 'staplerFullPayload',
            'keyboards'
            => $isVariant
                ? 'keyboardVariantPayload'
                : 'keyboardFullPayload',
            default => null
        };
    }
    private function extractPayloadStructure(
        array $data,
        string $parent = ''
    ): array {
        $fields = [];
        foreach ($data as $key => $value) {
            $fieldKey =
                $parent
                ? $parent . '.' . $key
                : $key;
            if (is_array($value)) {
                $first = $value[0] ?? null;
                if (is_array($first)) {
                    $fields = array_merge(
                        $fields,
                        $this->extractPayloadStructure(
                            $first,
                            $fieldKey
                        )
                    );
                    continue;
                }
            }
            $lastPart =
                collect(
                    explode('.', $fieldKey)
                )->last();
            if (
                in_array(
                    $lastPart,
                    $this->skipFields
                )
            ) {
                continue;
            }
            $fields[] = $fieldKey;
        }
        return $fields;
    }
}
