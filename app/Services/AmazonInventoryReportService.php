<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use SellingPartnerApi\Seller\ReportsV20210630\Dto\CreateReportSpecification;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;

class AmazonInventoryReportService
{
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
        $connector = $this->amazonService->getSellerConnector($shop);

        $response = $connector
            ->reportsV20210630()
            ->getReportDocument(
                $reportDocumentId,
                'GET_FLAT_FILE_OPEN_LISTINGS_DATA'
            );

        $document = json_decode($response->body(), true);

        $gzipContent = file_get_contents($document['url']);

        if ($gzipContent === false) {
            throw new \Exception('Unable to download report');
        }

        return [
            'compression' => $document['compressionAlgorithm'] ?? null,
            'content' => $gzipContent,
        ];
    }

    private function extractReport(string $gzipContent): string
    {
        $content = gzdecode($gzipContent);

        if ($content === false) {
            throw new \Exception('Unable to unzip Amazon report.');
        }

        return $content;
    }

    /**
     * Step 4
     */
    public function parseReport(string $content, Shop $shop): array
    {
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



            $products[] = [
                'listing_id'          => $row['listing-id'] ?? null,
                'sku'                 => $row['seller-sku'] ?? null,
                'title'               => $row['item-name'] ?? null,
                'description'         => $row['item-description'] ?? null,
                'asin'                => $row['asin1'] ?? null,
                'price'               => $row['price'] ?? null,
                'quantity'            => (int) ($row['quantity'] ?? 0),
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

        $report = $this->createReport($shop, $marketplaceId);

        $reportId = $report['reportId'];

        $this->updateProgress($shop, 35, 'Waiting for Amazon...');

        do {

            sleep(5);

            $status = $this->getReport($shop, $reportId);
        } while (($status['processingStatus'] ?? '') !== 'DONE');

        $this->updateProgress($shop, 60, 'Downloading Report...');

        $download = $this->downloadReport(
            $shop,
            $status['reportDocumentId']
        );

        $this->updateProgress($shop, 80, 'Extracting Data...');

        $content = $this->extractReport(
            $download['content']
        );

        $this->updateProgress($shop, 95, 'Parsing Inventory...');

        $rows = $this->parseReport($content, $shop);

        $inventoryCacheKey = "amazon_inventory_{$shop->id}_{$marketplaceId}";
        $statusCacheKey = "amazon_inventory_status_{$shop->id}_{$marketplaceId}";
        $currentStatus = Cache::get($statusCacheKey, ['cache_version' => 0]);

        Cache::forever(
            $inventoryCacheKey,
            $rows
        );

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

        Log::info('Amazon inventory cached', [
            'key' => $inventoryCacheKey,
            'count' => count($rows),
        ]);

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
