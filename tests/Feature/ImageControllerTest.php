<?php

use App\Http\Controllers\ImageController;
use App\Models\Image;
use Illuminate\Http\Request;

it('returns shop images for the image picker', function () {
    $shop = new class {
        public $id = 1;
    };

    Image::where('shop_id', $shop->id)->delete();

    Image::create([
        'shop_id' => $shop->id,
        'image' => 'uploads/images/test-1.jpg',
    ]);

    Image::create([
        'shop_id' => $shop->id,
        'image' => 'uploads/images/test-2.jpg',
    ]);

    $request = new Request();
    $request->attributes->set('active_shop_model', $shop);

    $response = (new ImageController())->forSelection($request);

    expect($response->getStatusCode())->toBe(200);

    $payload = $response->getData(true);

    expect($payload['success'])->toBeTrue();
    expect($payload['images'])->toHaveCount(2);
    expect($payload['images'][0]['url'])->toContain('uploads/images/test-1.jpg');
});
