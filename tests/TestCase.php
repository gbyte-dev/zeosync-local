<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'mysql') {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                DB::statement('SET SESSION sql_mode = ""');
            } catch (\Throwable $e) {
                // Ignore if MySQL server is not connected during in-memory testing
            }
        }

        if (!Schema::hasTable('inventory_sync_operations')) {
            Schema::create('inventory_sync_operations', function (Blueprint $table) {
                $table->id();
                $table->string('operation_uuid')->nullable()->unique();
                $table->unsignedBigInteger('shop_id')->index();
                $table->unsignedBigInteger('webhook_event_id')->nullable();
                $table->unsignedBigInteger('mapping_id')->nullable()->index();
                $table->string('source_key')->nullable()->unique();
                $table->string('sku')->nullable()->default('');
                $table->string('amazon_sku')->nullable();
                $table->string('source')->default('manual_ui');
                $table->string('source_state')->nullable()->default('pending');
                $table->string('inventory_item_id')->nullable();
                $table->string('shopify_inventory_item_id')->nullable();
                $table->string('location_id')->nullable();
                $table->string('shopify_location_id')->nullable();
                $table->string('marketplace_id')->nullable();
                $table->integer('desired_quantity')->default(0);
                $table->unsignedInteger('requested_quantity')->nullable();
                $table->unsignedInteger('observed_quantity')->nullable();
                $table->integer('baseline_quantity')->nullable();
                $table->integer('delta')->default(0);
                $table->unsignedBigInteger('expected_inventory_version')->default(1);
                $table->string('status')->default('pending');
                $table->string('stage')->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(4);
                $table->text('error')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('last_dispatched_at')->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status'], 'idx_shop_status');
                $table->index(['shop_id', 'shopify_inventory_item_id', 'status'], 'idx_shop_item_status');
                $table->index(['shop_id', 'amazon_sku', 'status'], 'idx_shop_sku_status');
                $table->index(['status', 'created_at'], 'idx_status_created');
                $table->index(['status', 'last_dispatched_at'], 'idx_status_dispatched');
                $table->index(['status', 'processing_started_at'], 'idx_status_processing');
            });
        }

        if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'inventory_version')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->unsignedBigInteger('inventory_version')->default(1);
            });
        }

        if (Schema::hasTable('inventory_sync_operations')) {
            if (!Schema::hasColumn('inventory_sync_operations', 'baseline_quantity')) {
                Schema::table('inventory_sync_operations', function (Blueprint $table) {
                    $table->integer('baseline_quantity')->nullable();
                });
            }
            if (!Schema::hasColumn('inventory_sync_operations', 'expected_inventory_version')) {
                Schema::table('inventory_sync_operations', function (Blueprint $table) {
                    $table->unsignedBigInteger('expected_inventory_version')->default(1);
                });
            }
        }
    }
}
