<?php

use App\Models\AllProduct;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('allproducts')) {
        Schema::create('allproducts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('submission_status')->nullable();
            $table->timestamp('submitted_on')->nullable();
            $table->string('producttype')->nullable();
            $table->longText('final_json')->nullable();
            $table->longText('filled_json')->nullable();
            $table->unsignedBigInteger('schema_id')->nullable();
            $table->string('sku');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['user_id', 'sku'], 'allproducts_user_id_sku_unique');
        });
    }
});

it('verifies that the composite user_id and sku unique index exists', function () {
    $indexes = collect(Schema::getIndexes('allproducts'));

    $skuOnlyUniqueIndex = $indexes->first(function ($idx) {
        return $idx['name'] === 'allproducts_sku_unique';
    });
    expect($skuOnlyUniqueIndex)->toBeNull();

    $compositeUniqueIndex = $indexes->first(function ($idx) {
        return $idx['name'] === 'allproducts_user_id_sku_unique';
    });
    expect($compositeUniqueIndex)->not->toBeNull();
    expect($compositeUniqueIndex['unique'])->toBeTrue();
    expect($compositeUniqueIndex['columns'])->toEqual(['user_id', 'sku']);
});

it('allows two different shops to use the same SKU without collision', function () {
    $shopA_Id = 99101;
    $shopB_Id = 99102;
    $sku = 'SHARED-COLLISION-SKU-100';

    $productA = AllProduct::create([
        'user_id' => $shopA_Id,
        'sku' => $sku,
        'status' => 'draft',
    ]);

    $productB = AllProduct::create([
        'user_id' => $shopB_Id,
        'sku' => $sku,
        'status' => 'draft',
    ]);

    expect($productA->id)->toBeGreaterThan(0)
        ->and($productB->id)->toBeGreaterThan(0)
        ->and($productA->sku)->toBe($sku)
        ->and($productB->sku)->toBe($sku)
        ->and($productA->user_id)->toBe($shopA_Id)
        ->and($productB->user_id)->toBe($shopB_Id);
});

it('rejects duplicate SKUs within the same shop', function () {
    $shopId = 99103;
    $sku = 'SAME-SHOP-DUPLICATE-SKU-200';

    $product1 = AllProduct::create([
        'user_id' => $shopId,
        'sku' => $sku,
        'status' => 'draft',
    ]);

    expect($product1->id)->toBeGreaterThan(0);

    expect(function () use ($shopId, $sku) {
        AllProduct::create([
            'user_id' => $shopId,
            'sku' => $sku,
            'status' => 'draft',
        ]);
    })->toThrow(QueryException::class);
});
