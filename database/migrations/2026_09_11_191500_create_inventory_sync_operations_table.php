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
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->unique();
            $table->unsignedBigInteger('shop_id')->index();
            $table->unsignedBigInteger('mapping_id')->nullable()->index();
            $table->string('shopify_inventory_item_id');
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity');
            $table->string('source')->default('manual_ui');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, superseded
            $table->string('stage')->default('pending'); // pending, shopify_completed, amazon_accepted, completed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(4);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Compound Indexes for fast queries & sweeper recovery
            $table->index(['shop_id', 'status'], 'idx_shop_status');
            $table->index(['shop_id', 'shopify_inventory_item_id', 'status'], 'idx_shop_item_status');
            $table->index(['shop_id', 'amazon_sku', 'status'], 'idx_shop_sku_status');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['status', 'last_dispatched_at'], 'idx_status_dispatched');
            $table->index(['status', 'processing_started_at'], 'idx_status_processing');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_operations');
    }
};
