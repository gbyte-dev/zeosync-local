<?php
namespace App\Services\Amazon;
use RuntimeException;
use SellingPartnerApi\Enums\Endpoint;
class AmazonMarketplaceContext
{
    public function marketplaceId(object $shop): string
    {
        $marketplaceId = trim((string) ($shop->amazon_marketplace_id ?? ''));
        if ($marketplaceId === '') { return $this->defaultMarketplaceId(); }
        $supported = array_keys((array) config('amazon.marketplaces', []));
        if ($supported !== [] && !in_array($marketplaceId, $supported, true)) {
            throw new RuntimeException('Amazon marketplace is not supported by this deployment.');
        }
        return $marketplaceId;
    }
    public function endpoint(object $shop): Endpoint
    {
        try { return Endpoint::byMarketplaceId($this->marketplaceId($shop)); }
        catch (\Throwable $e) { throw new RuntimeException('Amazon marketplace endpoint could not be resolved.', previous: $e); }
    }
    public function defaultMarketplaceId(): string
    {
        $id = trim((string) config('amazon.default_marketplace_id', ''));
        if ($id === '') { throw new RuntimeException('Default Amazon marketplace is not configured.'); }
        return $id;
    }
}
