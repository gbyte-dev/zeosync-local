<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {

            if (! Schema::hasColumn('shop_subscriptions', 'trial_days')) {
                $table->unsignedInteger('trial_days')->default(0);
            }

            if (! Schema::hasColumn('shop_subscriptions', 'is_trial')) {
                $table->boolean('is_trial')->default(false);
            }

            if (! Schema::hasColumn('shop_subscriptions', 'trial_used')) {
                $table->boolean('trial_used')->default(false);
            }

            if (! Schema::hasColumn('shop_subscriptions', 'current_period_end')) {
                $table->timestamp('current_period_end')->nullable();
            }

            if (! Schema::hasColumn('shop_subscriptions', 'shopify_return_url')) {
                $table->text('shopify_return_url')->nullable();
            }

            if (! Schema::hasColumn('shop_subscriptions', 'shopify_confirmation_url')) {
                $table->text('shopify_confirmation_url')->nullable();
            }

            if (! Schema::hasColumn('shop_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {

            foreach (
                [
                    'started_at',
                    'activated_at',
                    'current_period_end',
                    'ended_at',
                    'cancelled_at',
                    'requested_plan_id',
                ] as $column
            ) {

                if (Schema::hasColumn('shop_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
