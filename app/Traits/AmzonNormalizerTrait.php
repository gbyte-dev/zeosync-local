<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait AmzonNormalizerTrait
{

    private function getAttributeValueAmz($product, string $name)
    {
        return $product->attributes
            ->firstWhere('attribute_name', $name)
            ?->attribute_value;
    }

}