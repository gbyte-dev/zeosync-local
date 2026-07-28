<?php

namespace App\Services\Amazon;

class ShopifyAmazonMapper
{
    /**
     * Convert Shopify Product to Amazon Attribute Array
     */
    public function map(array $product): array
    {
        $variant = $product['variants'][0] ?? [];

        return [

            // Basic
            'item_name'         => $this->text($product['title'] ?? ''),
            'brand'             => $this->text($product['vendor'] ?? 'Generic'),
            'manufacturer'      => $this->text($product['vendor'] ?? 'Generic'),

            // SKU
            'sku'                       => $this->text($variant['sku'] ?? ''),
            'model_name'                => $this->text($variant['sku'] ?? ''),
            'manufacturer_part_number'  => $this->text($variant['sku'] ?? ''),

            // Description
            'product_description' => $this->description($product['body_html'] ?? ''),
            'bullet_point'        => $this->bulletPoints($product['body_html'] ?? ''),

            // Variant
            'color' => $this->text($variant['option1'] ?? ''),

            // Price & Inventory
            'price'    => $variant['price'] ?? '',
            'quantity' => $variant['inventory_quantity'] ?? 0,

            // Weight
            'item_package_weight' => $this->weight($variant),
            'item_display_weight' => $this->weight($variant),

            // Dimensions
            'item_dimensions' => $this->dimensions(
                $variant['length'] ?? '',
                $variant['width'] ?? '',
                $variant['height'] ?? ''
            ),

            // Barcode
            'externally_assigned_product_identifier'
                => $this->text($variant['barcode'] ?? ''),

            // Images
            'main_product_image_locator'  => $this->mainImage($product),
            'other_product_image_locator' => $this->otherImages($product),

            // Defaults
            'country_of_origin'      => 'US',
            'condition_type'         => 'new_new',
            'merchant_suggested_asin'=> '',

            // Shopify Info
            'shopify_product_id' => $product['id'] ?? '',
            'shopify_variant_id' => $variant['id'] ?? '',
            'shopify_handle'     => $product['handle'] ?? '',
            'shopify_status'     => $product['status'] ?? '',
        ];
    }

    /**
     * Remove HTML
     */
    private function description($html): string
    {
        return trim(strip_tags($html));
    }

    /**
     * Bullet Points
     */
    private function bulletPoints($html): string
    {
        $text = strip_tags($html);

        $points = preg_split('/\r\n|\r|\n/', $text);

        $result = [];

        foreach ($points as $point) {

            $point = trim($point);

            if ($point == '') {
                continue;
            }

            $result[] = substr($point,0,500);

            if(count($result)==5){
                break;
            }
        }

        return implode("\n",$result);
    }

    /**
     * Weight
     * Output:
     * 200 g
     */
    private function weight(array $variant): string
    {
        if(empty($variant['weight'])){
            return '';
        }

        return trim(
            $variant['weight'].' '.$variant['weight_unit']
        );
    }

    /**
     * Dimensions
     * Output:
     * 10L*20W*5H
     */
    private function dimensions($length,$width,$height): string
    {
        if(
            empty($length) ||
            empty($width) ||
            empty($height)
        ){
            return '';
        }

        return "{$length}L*{$width}W*{$height}H";
    }

    /**
     * Generic Value + Unit
     * Output:
     * 10 cm
     * 250 ml
     */
    private function valueWithUnit($value,$unit): string
    {
        if(empty($value)){
            return '';
        }

        return trim($value.' '.$unit);
    }

    /**
     * Main Image
     */
    private function mainImage(array $product): string
    {
        return $product['image']['src'] ?? '';
    }

    /**
     * Other Images
     */
    private function otherImages(array $product): string
    {
        if(empty($product['images'])){
            return '';
        }

        $images=[];

        foreach($product['images'] as $index=>$image){

            if($index==0){
                continue;
            }

            $images[]=$image['src'];
        }

        return implode(',',$images);
    }

    /**
     * Generic Text
     */
    private function text($value,$default=''): string
    {
        return !empty($value)
            ? trim(strip_tags($value))
            : $default;
    }

    /**
     * Boolean
     */
    private function boolean($value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Integer
     */
    private function integer($value): int
    {
        return (int)$value;
    }

    /**
     * Float
     */
    private function decimal($value): float
    {
        return round((float)$value,2);
    }

    /**
     * Date
     */
    private function date($date): string
    {
        if(empty($date)){
            return '';
        }

        return date('Y-m-d',strtotime($date));
    }

    /**
     * Comma Separated Values
     */
    private function csv(array $values): string
    {
        return implode(',',array_filter($values));
    }

    /**
     * JSON Encode
     */
    private function json($value): string
    {
        return json_encode($value);
    }

    /**
     * Uppercase
     */
    private function upper($value): string
    {
        return strtoupper(trim($value));
    }

    /**
     * Lowercase
     */
    private function lower($value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Limit Characters
     */
    private function limit($text,$limit=500): string
    {
        return substr(trim(strip_tags($text)),0,$limit);
    }
}