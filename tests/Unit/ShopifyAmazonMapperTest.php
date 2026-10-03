<?php

use App\Services\Amazon\ShopifyAmazonMapper;

it('converts raw 12-digit Shopify barcode to UPC-prefixed identifier', function () {
    $mapper = new ShopifyAmazonMapper();

    $product = [
        'title' => 'Test Product',
        'vendor' => 'Test Vendor',
        'variants' => [
            [
                'id' => 12345,
                'sku' => 'TEST-SKU-1',
                'barcode' => '012345678905',
                'price' => '19.99',
                'inventory_quantity' => 10,
            ]
        ]
    ];

    $mapped = $mapper->map($product);

    expect($mapped['externally_assigned_product_identifier'])->toBe('UPC:012345678905');
});

it('preserves already prefixed barcodes unchanged', function (string $inputBarcode, string $expectedOutput) {
    $mapper = new ShopifyAmazonMapper();

    $product = [
        'title' => 'Test Product',
        'variants' => [
            [
                'barcode' => $inputBarcode,
            ]
        ]
    ];

    $mapped = $mapper->map($product);

    expect($mapped['externally_assigned_product_identifier'])->toBe($expectedOutput);
})->with([
    ['UPC:012345678905', 'UPC:012345678905'],
    ['EAN:1234567890123', 'EAN:1234567890123'],
    ['GTIN:12345678901234', 'GTIN:12345678901234'],
    ['upc:012345678905', 'UPC:012345678905'],
]);

it('returns empty string for missing or invalid barcodes without fabricating values', function ($invalidBarcode) {
    $mapper = new ShopifyAmazonMapper();

    $product = [
        'title' => 'Test Product',
        'variants' => [
            [
                'barcode' => $invalidBarcode,
            ]
        ]
    ];

    $mapped = $mapper->map($product);

    expect($mapped['externally_assigned_product_identifier'])->toBe('');
})->with([
    [''],
    [null],
    ['INVALID123'],
    ['12345'],
    ['01234567890123456'],
]);
