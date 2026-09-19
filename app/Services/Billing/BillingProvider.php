<?php

namespace App\Services\Billing;

use App\Models\AdminSetting;

class BillingProvider
{
    public const SHOPIFY = 'shopify';
    public const STRIPE = 'stripe';

    public const DEFAULT = self::SHOPIFY;
    public const FALLBACK = self::SHOPIFY;

    /**
     * Resolve the active billing provider.
     *
     * Priority:
     *   1. BILLING_PROVIDER env variable (config/billing.php)
     *   2. billing_provider admin setting (admin_settings table)
     *   3. Default: 'shopify'
     */
    public function provider(): string
    {
        return self::SHOPIFY;
    }

    public function isStripe(): bool
    {
        return $this->provider() === self::STRIPE;
    }

    public function isShopify(): bool
    {
        return $this->provider() === self::SHOPIFY;
    }

    public function label(): string
    {
        return config("billing.providers.{$this->provider()}.label", ucfirst($this->provider()));
    }
}