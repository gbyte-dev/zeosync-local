<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Plan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomPlanService
{
    public function create(array $data)
    {
        $shop = Shop::findOrFail($data['shop_id']);

        $prices = $data['prices'] ?? [];

        $features = collect($data['features'] ?? [])
            ->map(fn ($feature) => trim((string) $feature))
            ->filter(fn ($feature) => $feature !== '')
            ->values()
            ->all();

        $price = $prices['EVERY_30_DAYS']
            ?? $prices['ANNUAL']
            ?? 0;

        $planData = [
            'shop_id' => $shop->id,
            'name' => $shop->shop_name . ' Custom Plan',
            'slug' => Str::slug($shop->shop_name . '-custom-plan') . '-' . Str::lower(Str::random(6)),
            'price' => $price,
            'prices' => $prices,
            'stripe_price_ids' => [],
            'product_limit' => (int) ($data['product_limit'] ?? 0),
            'sync_limit' => (int) ($data['sync_limit'] ?? 0),
            'badge' => null,
            'description' => null,
            'features' => $features ?: null,
            'contact_button_text' => null,
            'is_active' => true,
            'is_enterprise' => true,
            'is_custom' => true,
            'ai_autofill' => !empty($data['ai_autofill']),
            'ai_single_field' => !empty($data['ai_single_field']),
        ];

        DB::beginTransaction();

        try {

            $stripeService = app(StripeService::class);

            $product = $stripeService->createProduct([
                'name'        => $planData['name'],
                'description' => $planData['description'],
                'shop_id'     => $shop->id,
            ]);

            if (!$product) {
                throw new \Exception('Unable to create Stripe product.');
            }

            $stripePriceIds = [];

            if (!empty($prices['EVERY_30_DAYS'])) {

                $monthlyPrice = $stripeService->createPrice(
                    $product->id,
                    (float) $prices['EVERY_30_DAYS'],
                    'month'
                );

                if (!$monthlyPrice) {
                    throw new \Exception('Unable to create monthly Stripe price.');
                }

                $stripePriceIds['EVERY_30_DAYS'] = $monthlyPrice->id;
            }

            if (!empty($prices['ANNUAL'])) {

                $yearlyPrice = $stripeService->createPrice(
                    $product->id,
                    (float) $prices['ANNUAL'],
                    'year'
                );

                if (!$yearlyPrice) {
                    throw new \Exception('Unable to create yearly Stripe price.');
                }

                $stripePriceIds['ANNUAL'] = $yearlyPrice->id;
            }

            $planData['stripe_price_ids'] = $stripePriceIds;
            $plan = Plan::create($planData);
            DB::commit();

            return $plan;
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Custom Plan Creation Failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            throw $e;
        }
    }
}
