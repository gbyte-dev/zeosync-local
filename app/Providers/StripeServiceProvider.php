<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;
use Stripe\StripeClient;
use App\Models\AdminSetting;

class StripeServiceProvider extends ServiceProvider
{
    /**
     * Register Stripe services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            // Get credentials from admin settings or env
            $secretKey = $this->getStripeSecretKey();

            return new StripeClient($secretKey);
        });

        $this->app->singleton('stripe', function () {
            return $this->app->make(StripeClient::class);
        });
    }

    /**
     * Bootstrap Stripe services.
     */
    public function boot(): void
    {
        $secretKey = $this->getStripeSecretKey();

        if (empty($secretKey)) {
            \Log::warning('Stripe secret key is not configured.');
            return;
        }

        Stripe::setApiKey($secretKey);
        Stripe::setApiVersion('2024-06-20');
    }

    /**
     * Get Stripe secret key from settings or env
     */
    private function getStripeSecretKey(): string
    {
        // Try to get from AdminSettings first (if you have SMTP-like settings)
        try {
            $setting = AdminSetting::where('option_key', 'stripe_secret_key')->first();
            if ($setting && !empty($setting->option_value)) {
                \Log::info('Stripe key loaded from AdminSettings');
                return $setting->option_value;
            }
        } catch (\Exception $e) {
            \Log::warning('Could not load Stripe key from AdminSettings: ' . $e->getMessage());
        }

        // Fallback to env variable
        $envKey = env('STRIPE_SECRET_KEY', '');
        if (!empty($envKey)) {
            \Log::info('Stripe key loaded from environment');
            return $envKey;
        }

        \Log::error('Stripe secret key is not configured!');
        return '';
    }

    /**
     * Get Stripe publishable key
     */
    public static function getPublishableKey(): string
    {
        try {
            $setting = AdminSetting::where('option_key', 'stripe_publishable_key')->first();
            if ($setting && !empty($setting->option_value)) {
                return $setting->option_value;
            }
        } catch (\Exception $e) {
            // Fall back to env
        }

        return env('STRIPE_PUBLISHABLE_KEY', '');
    }

    /**
     * Get Stripe webhook secret
     */
    public static function getWebhookSecret(): string
    {
        try {
            $setting = AdminSetting::where('option_key', 'stripe_webhook_secret')->first();
            if ($setting && !empty($setting->option_value)) {
                return $setting->option_value;
            }
        } catch (\Exception $e) {
            // Fall back to env
        }

        return env('STRIPE_WEBHOOK_SECRET', '');
    }
}
