<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonInventoryReportService;
use App\Services\AmazonService;
use App\Services\InventoryCacheService;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->string('amazon_endpoint')->nullable();
            $table->boolean('is_active')->default(1);
            $table->string('store_status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('quantity')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('sync_limit')->default(100);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    Shop::truncate();
    ProductMarketplaceMapping::truncate();
    Cache::flush();
});

function createSafetyTestShop(int $id = 1, string $domain = 'safety-test.myshopify.com'): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = "Safety Store {$id}";
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->access_token_expires_at = now()->addDays(30);
    $shop->refresh_token = "refresh-{$id}";
    $shop->refresh_token_expires_at = now()->addDays(60);
    $shop->is_active = 1;
    $shop->store_status = 'active';
    $shop->amazon_seller_id = "SELLER_{$id}";
    $shop->amazon_refresh_token = 'valid_refresh_token';
    $shop->amazon_marketplace_id = 'ATVPDKIKX0DER';
    $shop->amazon_mws_region = 'na';
    $shop->save();

    return $shop;
}

function sampleTsvData(): string
{
    $headers = [
        'item-name', 'item-description', 'listing-id', 'seller-sku', 'price',
        'quantity', 'open-date', 'asin1', 'fulfillment-channel', 'merchant-shipping-group', 'status'
    ];
    $row1 = [
        'Test Item 1', 'Sample Description 1', '1001', 'SKU-AMZ-1', '19.99',
        '42', '2026-01-01', 'B001TEST', 'DEFAULT', 'Standard', 'Active'
    ];
    $row2 = [
        'Test Item 2', 'Sample Description 2', '1002', 'SKU-AMZ-2', '29.99',
        '15', '2026-01-01', 'B002TEST', 'DEFAULT', 'Standard', 'Active'
    ];

    return implode("\t", $headers) . "\n" . implode("\t", $row1) . "\n" . implode("\t", $row2);
}

function createReportMockSetup($createResponse, $getStatusResponses, $getDocumentResponse = null): AmazonService
{
    $reportsApiMock = Mockery::mock();

    if ($createResponse instanceof \Closure) {
        $reportsApiMock->shouldReceive('createReport')->andReturnUsing($createResponse);
    } elseif ($createResponse instanceof \Throwable) {
        $reportsApiMock->shouldReceive('createReport')->andThrow($createResponse);
    } elseif ($createResponse !== null) {
        $reportsApiMock->shouldReceive('createReport')->andReturn(
            Mockery::mock(['body' => json_encode($createResponse)])
        );
    }

    if (is_array($getStatusResponses)) {
        $statusSeq = $reportsApiMock->shouldReceive('getReport');
        foreach ($getStatusResponses as $resp) {
            if ($resp instanceof \Closure) {
                $statusSeq->andReturnUsing($resp);
            } elseif ($resp instanceof \Throwable) {
                $statusSeq->andThrow($resp);
            } else {
                $statusSeq->andReturn(Mockery::mock(['body' => json_encode($resp)]));
            }
        }
    }

    if ($getDocumentResponse instanceof \Closure) {
        $reportsApiMock->shouldReceive('getReportDocument')->andReturnUsing($getDocumentResponse);
    } elseif ($getDocumentResponse instanceof \Throwable) {
        $reportsApiMock->shouldReceive('getReportDocument')->andThrow($getDocumentResponse);
    } elseif ($getDocumentResponse !== null) {
        $reportsApiMock->shouldReceive('getReportDocument')->andReturn(
            Mockery::mock(['body' => json_encode($getDocumentResponse)])
        );
    }

    $connectorMock = Mockery::mock();
    $connectorMock->shouldReceive('reportsV20210630')->andReturn($reportsApiMock);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $amazonServiceMock->shouldReceive('getSellerConnector')->andReturn($connectorMock);

    return $amazonServiceMock;
}

// -------------------------------------------------------------------------
// 1. Existing Amazon cache remains available while refresh is running
// -------------------------------------------------------------------------
test('1. Existing Amazon cache remains available while refresh is running', function () {
    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";
    $statusKey = "amazon_inventory_status_{$shop->id}_ATVPDKIKX0DER";

    $existingProducts = [
        ['sku' => 'SKU-OLD-1', 'quantity' => 50, 'status' => 'Active'],
    ];
    Cache::forever($cacheKey, $existingProducts);
    Cache::forever($statusKey, [
        'refreshing'     => true, // In-progress refresh
        'sync_completed' => true,
        'last_synced_at' => now()->toDateTimeString(),
    ]);

    $inventoryCacheService = app(InventoryCacheService::class);
    $result = $inventoryCacheService->getAmazonInventory($shop, 'ATVPDKIKX0DER');

    expect($result['products'])->toHaveCount(1)
        ->and($result['products'][0]['sku'])->toBe('SKU-OLD-1')
        ->and($result['status']['refreshing'])->toBeTrue();
});

// -------------------------------------------------------------------------
// 2. Report creation failure preserves old inventory
// -------------------------------------------------------------------------
test('2. Report creation failure preserves old inventory', function () {
    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";
    $statusKey = "amazon_inventory_status_{$shop->id}_ATVPDKIKX0DER";

    Cache::forever($cacheKey, [['sku' => 'SKU-PRESERVED', 'quantity' => 10]]);
    Cache::forever($statusKey, ['sync_completed' => true, 'last_synced_at' => now()->toDateTimeString()]);

    $amazonServiceMock = createReportMockSetup(
        new \Exception('Amazon API 429 Too Many Requests'),
        []
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $inventoryCacheService = new InventoryCacheService();
    $reflection = new \ReflectionClass($inventoryCacheService);
    $property = $reflection->getProperty('amazonInventoryReportService');
    $property->setAccessible(true);
    $property->setValue($inventoryCacheService, $reportService);

    try {
        $inventoryCacheService->refreshAmazonInventory($shop, 'ATVPDKIKX0DER');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('Amazon API 429');
    }

    // Existing cache must still exist
    expect(Cache::get($cacheKey))->toHaveCount(1)
        ->and(Cache::get($cacheKey)[0]['sku'])->toBe('SKU-PRESERVED');

    // UI reads existing cache cleanly
    $getRes = $inventoryCacheService->getAmazonInventory($shop, 'ATVPDKIKX0DER');
    expect($getRes['products'])->toHaveCount(1)
        ->and($getRes['products'][0]['sku'])->toBe('SKU-PRESERVED');
});

// -------------------------------------------------------------------------
// 3. Report download failure preserves old inventory
// -------------------------------------------------------------------------
test('3. Report download failure preserves old inventory', function () {
    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";

    Cache::forever($cacheKey, [['sku' => 'SKU-OLD-DATA', 'quantity' => 99]]);

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_123'],
        [['processingStatus' => 'DONE', 'reportDocumentId' => 'doc_123']],
        ['url' => 'https://invalid-nonexistent-s3-url.example.com/missing.tsv']
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    $inventoryCacheService = new InventoryCacheService();
    $reflection = new \ReflectionClass($inventoryCacheService);
    $property = $reflection->getProperty('amazonInventoryReportService');
    $property->setAccessible(true);
    $property->setValue($inventoryCacheService, $reportService);

    try {
        $inventoryCacheService->refreshAmazonInventory($shop, 'ATVPDKIKX0DER');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toBeString();
    }

    expect(Cache::get($cacheKey))->toHaveCount(1)
        ->and(Cache::get($cacheKey)[0]['sku'])->toBe('SKU-OLD-DATA');
});

// -------------------------------------------------------------------------
// 4. CANCELLED status stops polling immediately
// -------------------------------------------------------------------------
test('4. CANCELLED status stops polling immediately without hanging', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_cancelled'],
        [
            ['processingStatus' => 'IN_PROGRESS'],
            ['processingStatus' => 'CANCELLED'],
        ]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report processing failed with status: CANCELLED');
});

// -------------------------------------------------------------------------
// 5. FATAL status stops polling immediately
// -------------------------------------------------------------------------
test('5. FATAL status stops polling immediately without hanging', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_fatal'],
        [
            ['processingStatus' => 'FATAL'],
        ]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report processing failed with status: FATAL');
});

// -------------------------------------------------------------------------
// 6. FAILED status stops polling immediately if returned
// -------------------------------------------------------------------------
test('6. FAILED status stops polling immediately without hanging', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_failed'],
        [
            ['processingStatus' => 'FAILED'],
        ]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report processing failed with status: FAILED');
});

// -------------------------------------------------------------------------
// 7. Unknown or missing status does not cause infinite polling
// -------------------------------------------------------------------------
test('7. Unknown or missing status fails safely without infinite loop', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_unknown'],
        [
            ['processingStatus' => 'UNEXPECTED_STATUS_CODE'],
        ]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report returned unexpected status');
});

// -------------------------------------------------------------------------
// 8. Polling stops after configured maximum attempts / timeout
// -------------------------------------------------------------------------
test('8. Polling stops after configured maximum attempts without hanging', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_timeout'],
        array_fill(0, 6, ['processingStatus' => 'IN_PROGRESS'])
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    expect($reportService->maxPollingAttempts)->toBe(60);

    $reportService->maxPollingAttempts = 5;
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report polling timed out after 5 attempts.');
});

// -------------------------------------------------------------------------
// 9. DONE without reportDocumentId fails safely
// -------------------------------------------------------------------------
test('9. DONE without reportDocumentId fails safely', function () {
    $shop = createSafetyTestShop();

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_done_no_doc'],
        [
            ['processingStatus' => 'DONE', 'reportDocumentId' => null],
        ]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    expect(fn() => $reportService->syncInventory($shop, 'ATVPDKIKX0DER'))
        ->toThrow(\Exception::class, 'Amazon report completed with status DONE but returned no reportDocumentId.');
});

// -------------------------------------------------------------------------
// 10. Plain uncompressed TSV content is handled successfully
// -------------------------------------------------------------------------
test('10. Plain uncompressed TSV content is extracted and parsed successfully', function () {
    $shop = createSafetyTestShop();
    $tsvContent = sampleTsvData();

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $reportService = new AmazonInventoryReportService($amazonServiceMock);

    $extracted = $reportService->extractReport($tsvContent, null);
    expect($extracted)->toBe($tsvContent);

    $parsed = $reportService->parseReport($extracted, $shop);
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['sku'])->toBe('SKU-AMZ-1')
        ->and($parsed[0]['quantity'])->toBe(42)
        ->and($parsed[1]['sku'])->toBe('SKU-AMZ-2')
        ->and($parsed[1]['quantity'])->toBe(15);
});

// -------------------------------------------------------------------------
// 11. GZIP-compressed TSV content is handled successfully
// -------------------------------------------------------------------------
test('11. GZIP-compressed TSV content is decompressed and parsed successfully', function () {
    $shop = createSafetyTestShop();
    $tsvContent = sampleTsvData();
    $gzippedContent = gzencode($tsvContent);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $reportService = new AmazonInventoryReportService($amazonServiceMock);

    // Test with compression algorithm header = GZIP
    $extracted = $reportService->extractReport($gzippedContent, 'GZIP');
    expect($extracted)->toBe($tsvContent);

    // Test with compression algorithm = null (magic-byte detection)
    $extractedFromMagicBytes = $reportService->extractReport($gzippedContent, null);
    expect($extractedFromMagicBytes)->toBe($tsvContent);

    $parsed = $reportService->parseReport($extracted, $shop);
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['sku'])->toBe('SKU-AMZ-1')
        ->and($parsed[1]['sku'])->toBe('SKU-AMZ-2');
});

// -------------------------------------------------------------------------
// 12. Invalid / corrupt report content does not overwrite active cache
// -------------------------------------------------------------------------
test('12. Corrupt GZIP data throws exception and preserves active cache', function () {
    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";
    Cache::forever($cacheKey, [['sku' => 'SKU-ORIGINAL', 'quantity' => 77]]);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $reportService = new AmazonInventoryReportService($amazonServiceMock);

    $corruptGzip = "\x1f\x8b\x08corrupted_invalid_gzip_stream_bytes";

    expect(fn() => $reportService->extractReport($corruptGzip, 'GZIP'))
        ->toThrow(\Exception::class, 'Unable to unzip Amazon report.');

    expect(Cache::get($cacheKey))->toHaveCount(1)
        ->and(Cache::get($cacheKey)[0]['sku'])->toBe('SKU-ORIGINAL');
});

// -------------------------------------------------------------------------
// 13. Empty parsed rows do not wipe existing non-empty inventory
// -------------------------------------------------------------------------
test('13. Empty parsed rows do not wipe existing non-empty inventory', function () {
    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";
    Cache::forever($cacheKey, [
        ['sku' => 'SKU-PERSISTENT-1', 'quantity' => 10],
        ['sku' => 'SKU-PERSISTENT-2', 'quantity' => 20],
    ]);

    // Temp file with empty TSV header only
    $emptyTsv = "item-name\titem-description\tlisting-id\tseller-sku\tprice\tquantity\topen-date\tasin1\tfulfillment-channel\tmerchant-shipping-group\tstatus\n";
    $tempFile = tempnam(sys_get_temp_dir(), 'amz_test_');
    file_put_contents($tempFile, $emptyTsv);

    $amazonServiceMock = createReportMockSetup(
        ['reportId' => 'rep_empty'],
        [['processingStatus' => 'DONE', 'reportDocumentId' => 'doc_empty']],
        ['url' => $tempFile]
    );

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    $result = $reportService->syncInventory($shop, 'ATVPDKIKX0DER');

    @unlink($tempFile);

    // Existing cache must NOT have been replaced by empty array []
    $cached = Cache::get($cacheKey);
    expect($cached)->toHaveCount(2)
        ->and($cached[0]['sku'])->toBe('SKU-PERSISTENT-1')
        ->and($result)->toHaveCount(2);
});

// -------------------------------------------------------------------------
// 14. Manual Amazon refresh does not delete existing inventory cache
// -------------------------------------------------------------------------
test('14. Manual Amazon refresh does not delete existing inventory cache', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $shop = createSafetyTestShop();
    $cacheKey = "amazon_inventory_{$shop->id}_ATVPDKIKX0DER";
    Cache::forever($cacheKey, [['sku' => 'SKU-KEPT-ALIVE', 'quantity' => 88]]);

    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);

    // Call GET /inventory/refresh?type=amazon
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->getJson('/inventory/refresh?type=amazon');

    $response->assertOk();

    // Verify cache was NOT forgotten
    expect(Cache::has($cacheKey))->toBeTrue()
        ->and(Cache::get($cacheKey)[0]['sku'])->toBe('SKU-KEPT-ALIVE');

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncAmazonInventoryJob::class);
});

// -------------------------------------------------------------------------
// 15. getReportDocument receives the same report type used by createReport
// -------------------------------------------------------------------------
test('15. getReportDocument receives GET_MERCHANT_LISTINGS_ALL_DATA report type', function () {
    $shop = createSafetyTestShop();
    $capturedReportType = null;

    $tempFile = tempnam(sys_get_temp_dir(), 'amz_test_');
    file_put_contents($tempFile, sampleTsvData());

    $reportsApiMock = Mockery::mock();
    $reportsApiMock->shouldReceive('createReport')
        ->andReturn(Mockery::mock(['body' => json_encode(['reportId' => 'rep_match_type'])]));

    $reportsApiMock->shouldReceive('getReport')
        ->andReturn(Mockery::mock(['body' => json_encode(['processingStatus' => 'DONE', 'reportDocumentId' => 'doc_match_type'])]));

    $reportsApiMock->shouldReceive('getReportDocument')
        ->with('doc_match_type', 'GET_MERCHANT_LISTINGS_ALL_DATA')
        ->andReturnUsing(function ($docId, $reportType) use (&$capturedReportType, $tempFile) {
            $capturedReportType = $reportType;
            return Mockery::mock(['body' => json_encode(['url' => $tempFile, 'compressionAlgorithm' => null])]);
        });

    $connectorMock = Mockery::mock();
    $connectorMock->shouldReceive('reportsV20210630')->andReturn($reportsApiMock);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $amazonServiceMock->shouldReceive('getSellerConnector')->andReturn($connectorMock);

    $reportService = new AmazonInventoryReportService($amazonServiceMock);
    $reportService->pollingIntervalSeconds = 0;

    $reportService->syncInventory($shop, 'ATVPDKIKX0DER');

    @unlink($tempFile);

    expect($capturedReportType)->toBe('GET_MERCHANT_LISTINGS_ALL_DATA');
});
