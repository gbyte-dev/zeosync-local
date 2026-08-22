<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {
            $table->string('shopify_subscription_gid')
                ->nullable()
                ->after('shopify_confirmation_url');

            $table->string('billing_interval')
                ->nullable()
                ->after('billing_cycle_months');

            $table->string('currency_code', 10)
                ->nullable()
                ->after('billing_interval');

            $table->boolean('is_test')
                ->default(false)
                ->after('trial_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_subscription_gid',
                'billing_interval',
                'currency_code',
                'is_test',
            ]);
        });
    }
};