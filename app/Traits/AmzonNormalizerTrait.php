<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait AmzonNormalizerTrait
{

private function getAttributeValueAmz($product, string $name, mixed $default = null): mixed
{
    $attribute = $product->attributes
        ->first(fn ($item) => $item->getAttribute('attribute_name') === $name);

     return  $attribute?->getAttribute('attribute_value') ?? $default;
}

}
