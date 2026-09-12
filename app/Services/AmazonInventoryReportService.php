<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use SellingPartnerApi\Seller\ReportsV20210630\Dto\CreateReportSpecification;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;

class AmazonInventoryReportService
{
    public int $maxPollingAttempts = 60;
    public int $pollingIntervalSeconds = 5;

    public function __construct(
        private readonly AmazonService $amazonService
    ) {}

    /**
     * Step 1
     */
    public function createReport($shop, string $marketplaceId)
    {
        try {

            $connector = $this->amazonService->getSellerConnector($shop);

            $request = new CreateReportSpecification(
                reportType: 'GET_MERCHANT_LISTINGS_ALL_DATA',
                marketplaceIds: [$marketplaceId]
            );

            $response = $connector
                ->reportsV20210630()
                ->createReport($request);

            $data = json_decode($response->body(), true);

            Log::info('Amazon Report Created', $data);

            return $data;
        } catch (\Throwable $e) {

            Log::error('Create Report Failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Step 2
     */
    public function getReport($shop, string $reportId)
    {
        try {

            $connector = $this->amazonService->getSellerConnector($shop);

            $response = $connector
                ->reportsV20210630()
                ->getReport($reportId);

            $data = json_decode($response->body(), true);

            Log::info('Amazon Report Status', $data);

            return $data;
        } catch (\Throwable $e) {

            Log::error('Get Report Failed', [
                'reportId' => $reportId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Step 3
     */
    public function downloadReport($shop, string $reportDocumentId)
    {
        if (empty($reportDocumentId)) {
            throw new \InvalidArgumentException('Amazon reportDocumentId is required for report download.');
        }

        $connector = $this->amazonService->getSellerConnector($shop);

        $response = $connector
            ->reportsV20210630()
            ->getReportDocument(
                $reportDocumentId,
                'GET_MERCHANT_LISTINGS_ALL_DATA'
            );

        $document = json_decode($response->body(), true);

        if (empty($document) || empty($document['url'])) {
            throw new \Exception('Amazon report document response did not contain a download URL.');
        }

        $content = @file_get_contents($document['url']);

        if ($content === false || $content === '') {
            throw new \Exception('Unable to download report content from Amazon document URL.');
        }

        return [
            'compression' => $document['compressionAlgorithm'] ?? null,
            'content'     => $content,
        ];
    }

    public function extractReport(string $content, ?string $compression = null): string
    {
        if (trim($content) === '') {
            throw new \Exception('Amazon report content is empty.');
        }

        $isGzip = (strtoupper((string) $compression) === 'GZIP')
            || (strlen($content) >= 2 && substr($content, 0, 2) === "\x1f\x8b");

        if ($isGzip) {
            $decompressed = @gzdecode($content);

            if ($decompressed === false) {
                throw new \Exception('Unable to unzip Amazon report.');
            }

            if (trim($decompressed) === '') {
                throw new \Exception('Amazon report decompressed content is empty.');
            }

            return $decompressed;
        }

        return $content;
    }

    /**
     * Step 4
     */
    public function parseReport(
        string $content,
        Shop $shop,
        ?\Carbon\Carbon $reportSnapshotTime = null
    ): array {
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        $header = array_map(function ($column) {
            return trim(
                preg_replace('/^\xEF\xBB\xBF/', '', $column)
            );
        }, str_getcsv(array_shift($lines), "\t"));

        $mappings = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->get()
            ->keyBy('amazon_sku');

        $products = [];

        foreach ($lines as $line) {

            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, "\t");
            if (count($header) !== count($values)) {

                Log::error('Header / Value count mismatch', [
                    'header_count' => count($header),
                    'value_count'  => count($values),
                    'line'         => $line,
                    'values'       => $values,
                ]);

                continue;
            }

            $row = array_combine($header, $values);

            $mapping = $mappings[$row['seller-sku'] ?? ''] ?? null;
            $isMapped = $mapping
                && !empty($mapping->shopify_variant_id)
                && !empty($mapping->amazon_sku);

            $reportQty = (int) ($row['quantity'] ?? 0);
            $finalQty = $reportQty;

            if ($isMapped && !empty($mapping->last_synced_at) && $reportSnapshotTime) {
                try {
                    $lastSynced = \Carbon\Carbon::parse($mapping->last_synced_at);
                    if ($lastSynced->greaterThan($reportSnapshotTime) && $mapping->quantity !== null && $mapping->quantity !== '') {
                        $finalQty = (int) $mapping->quantity;
                        Log::info('Stale Amazon report quantity overridden by more recent manual DB sync', [
                            'shop_id'              => $shop->id,
                            'sku'                  => $row['seller-sku'] ?? null,
                            'report_quantity'      => $reportQty,
                            'mapping_quantity'     => $finalQty,
                            'last_synced_at'       => $mapping->last_synced_at,
                            'report_snapshot_time' => $reportSnapshotTime->toDateTimeString(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to compare last_synced_at timestamp during report parsing', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $products[] = [
                'listing_id'          => $row['listing-id'] ?? null,
                'sku'                 => $row['seller-sku'] ?? null,
                'title'               => $row['item-name'] ?? null,
                'description'         => $row['item-description'] ?? null,
                'asin'                => $row['asin1'] ?? null,
                'price'               => $row['price'] ?? null,
                'quantity'            => $finalQty,
                'status'              => $row['status'] ?? null,
                'fulfillment_channel' => $row['fulfillment-channel'] ?? null,
                'shipping_group'      => $row['merchant-shipping-group'] ?? null,

                'is_mapped' => $isMapped,

                'mapped_shopify_product_id' => $isMapped ? $mapping->shopify_product_id : null,
                'mapped_shopify_variant_id' => $isMapped ? $mapping->shopify_variant_id : null,
                'mapping_id' => $isMapped ? $mapping->id : null,
            ];
        }

        return $products;
    }
    /**
     * Step 5
     */
    public function syncInventory($shop, ?string $marketplaceId = null)
    {
        $marketplaceId = $marketplaceId ?: ($shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER');

        $this->updateProgress($shop, 0, 'Preparing...');
        $this->updateProgress($shop, 10, 'Creating Report...');

        $reportSnapshotTime = now();
        $report = $this->createReport($shop, $marketplaceId);
        $reportId = $report['reportId'] ?? null;

        if (empty($reportId)) {
            throw new \Exception('Failed to create Amazon report: missing reportId in response.');
        }

        $this->updateProgress($shop, 35, 'Waiting for Amazon...');

        $attempt = 0;
        $status = null;
        $maxAttempts = $this->maxPollingAttempts ?? 60;
        $interval = $this->pollingIntervalSeconds ?? 5;

        do {
            $attempt++;
            if ($interval > 0) {
                sleep($interval);
            }

            $status = $this->getReport($shop, $reportId);
            $processingStatus = $status['processingStatus'] ?? null;

            if ($processingStatus === 'DONE') {
                break;
            }

            if (in_array($processingStatus, ['CANCELLED', 'FATAL', 'FAILED'], true)) {
                Log::error('Amazon report processing failed with terminal status', [
                    'shop_id'           => $shop->id ?? null,
                    'report_id'         => $reportId,
                    'processing_status' => $processingStatus,
                    'attempt'           => $attempt,
                ]);
                throw new \Exception("Amazon report processing failed with status: {$processingStatus}");
            }

            if (!in_array($processingStatus, ['SUBMITTED', 'IN_QUEUE', 'IN_PROGRESS'], true)) {
                Log::warning('Amazon report returned unknown processing status', [
                    'shop_id'           => $shop->id ?? null,
                    'report_id'         => $reportId,
                    'processing_status' => $processingStatus,
                    'attempt'           => $attempt,
                ]);
                throw new \Exception("Amazon report returned unexpected status: " . ($processingStatus ?? 'null'));
            }

            if ($attempt >= $maxAttempts) {
                Log::error('Amazon report polling timed out', [
                    'shop_id'      => $shop->id ?? null,
                    'report_id'    => $reportId,
                    'max_attempts' => $maxAttempts,
                ]);
                throw new \Exception("Amazon report polling timed out after {$maxAttempts} attempts.");
            }
        } while (true);

        if (empty($status['reportDocumentId'])) {
            throw new \Exception('Amazon report completed with status DONE but returned no reportDocumentId.');
        }

        if (!empty($status['createdTime'])) {
            try {
                $reportSnapshotTime = \Carbon\Carbon::parse($status['createdTime']);
            } catch (\Throwable) {
                // Fallback to recorded $reportSnapshotTime
            }
        }

        $this->updateProgress($shop, 60, 'Downloading Report...');

        $download = $this->downloadReport(
            $shop,
            $status['reportDocumentId']
        );

        $this->updateProgress($shop, 80, 'Extracting Data...');

        $content = $this->extractReport(
            $download['content'],
            $download['compression'] ?? null
        );

        $this->updateProgress($shop, 95, 'Parsing Inventory...');

        $rows = $this->parseReport($content, $shop, $reportSnapshotTime);

        $inventoryCacheKey = "amazon_inventory_{$shop->id}_{$marketplaceId}";
        $statusCacheKey = "amazon_inventory_status_{$shop->id}_{$marketplaceId}";
        $currentStatus = Cache::get($statusCacheKey, ['cache_version' => 0]);

        if (empty($rows) && Cache::has($inventoryCacheKey) && !empty(Cache::get($inventoryCacheKey))) {
            Log::warning('Amazon report produced 0 rows while existing inventory cache is non-empty. Preserving existing cache.', [
                'shop_id'        => $shop->id,
                'marketplace_id' => $marketplaceId,
            ]);
            $rows = Cache::get($inventoryCacheKey, []);
        } else {
            Cache::forever(
                $inventoryCacheKey,
                $rows
            );
        }

        Cache::forever(
            $statusCacheKey,
            [
                'refreshing'     => false,
                'sync_completed' => true,
                'last_synced_at' => now()->toDateTimeString(),
                'cache_version'  => (int) ($currentStatus['cache_version'] ?? 0) + 1,
            ]
        );

        $this->updateProgress($shop, 100, 'Completed');

        return $rows;
    }

    private function updateProgress($shop, int $percent, string $message): void
    {
        Cache::put(
            "amazon_progress_{$shop->shop}",
            [
                'percent' => $percent,
                'message' => $message,
            ],
            now()->addMinutes(5)
        );
    }
}
