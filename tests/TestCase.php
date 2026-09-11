<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!\Illuminate\Support\Facades\Schema::hasTable('inventory_sync_operations')) {
            \Illuminate\Support\Facades\Schema::create('inventory_sync_operations', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('operation_uuid')->unique();
                $table->unsignedBigInteger('shop_id')->index();
                $table->unsignedBigInteger('mapping_id')->nullable()->index();
                $table->string('shopify_inventory_item_id');
                $table->string('shopify_location_id')->nullable();
                $table->string('amazon_sku')->nullable();
                $table->integer('desired_quantity');
                $table->string('source')->default('manual_ui');
                $table->string('status')->default('pending');
                $table->string('stage')->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(4);
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('last_dispatched_at')->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status'], 'idx_shop_status');
                $table->index(['shop_id', 'shopify_inventory_item_id', 'status'], 'idx_shop_item_status');
                $table->index(['shop_id', 'amazon_sku', 'status'], 'idx_shop_sku_status');
                $table->index(['status', 'created_at'], 'idx_status_created');
                $table->index(['status', 'last_dispatched_at'], 'idx_status_dispatched');
                $table->index(['status', 'processing_started_at'], 'idx_status_processing');

            });
        }
    }
}

