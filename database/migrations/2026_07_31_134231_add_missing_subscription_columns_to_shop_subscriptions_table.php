<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {

            if (!Schema::hasColumn('shop_subscriptions', 'status')) {
                $table->string('status')->default('active')->after('plan_id');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'price')) {
                $table->decimal('price', 10, 2)->default(0.00)->after('status');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'billing_cycle_months')) {
                $table->unsignedInteger('billing_cycle_months')->default(1)->after('price');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('billing_cycle_months');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'trial_days')) {
                $table->unsignedInteger('trial_days')->default(0)->after('trial_ends_at');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'is_trial')) {
                $table->boolean('is_trial')->default(false)->after('trial_days');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'trial_used')) {
                $table->boolean('trial_used')->default(false)->after('is_trial');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'current_period_end')) {
                $table->timestamp('current_period_end')->nullable()->after('trial_used');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'shopify_return_url')) {
                $table->text('shopify_return_url')->nullable()->after('current_period_end');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'shopify_confirmation_url')) {
                $table->text('shopify_confirmation_url')->nullable()->after('shopify_return_url');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('shopify_confirmation_url');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('started_at');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('activated_at');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('ended_at');
            }

            if (!Schema::hasColumn('shop_subscriptions', 'requested_plan_id')) {
                $table->integer('requested_plan_id')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        // Production safety: do not drop columns.
    }
};