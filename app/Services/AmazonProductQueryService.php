<?php

namespace App\Services;

use App\Models\AllProduct;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;

class AmazonProductQueryService
{
    /**
     * Get the base Amazon Products collection for a shop (the single source of truth for Products -> Amazon Products).
     */
    public function getProductsForShop(int|Shop $shop, ?int $parentId = null): Collection
    {
        $shopId = $shop instanceof Shop ? $shop->id : (int) $shop;

        if ($parentId !== null) {
            return AllProduct::with('attributes', 'schema')
                ->where('user_id', $shopId)
                ->where('parent_id', $parentId)
                ->get();
        }

        return AllProduct::with('attributes', 'schema')
            ->where('user_id', $shopId)
            ->whereNull('parent_id')
            ->where(function ($query) {
                $query->whereNotNull('submission_status')
                    ->orWhere('status', 'draft');
            })
            ->get();
    }
}
