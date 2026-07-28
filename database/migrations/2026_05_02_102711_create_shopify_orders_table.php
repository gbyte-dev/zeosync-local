<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('shop_id');

            $table->string('shopify_order_id')->nullable();
            $table->string('admin_graphql_api_id')->nullable();
            $table->string('shopify_event_id')->nullable();
            $table->string('shopify_webhook_id')->nullable();

            $table->string('order_number')->nullable()->index();
            $table->string('name')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->string('customer_first_name')->nullable()->index();
            $table->string('customer_last_name')->nullable()->index();
            $table->string('customer_phone')->nullable();
            $table->string('phone')->nullable();

            $table->string('financial_status')->nullable()->index();
            $table->string('fulfillment_status')->nullable();

            $table->string('currency', 10)->nullable();

            $table->decimal('subtotal_price', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_discounts', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);

            $table->integer('line_items_count')->default(0);

            $table->string('source_name')->nullable();
            $table->text('tags')->nullable();
            $table->text('note')->nullable();

            // JSON fields
            $table->json('customer')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('line_items')->nullable();
            $table->json('discount_codes')->nullable();
            $table->json('shipping_lines')->nullable();
            $table->json('tax_lines')->nullable();
            $table->json('raw_payload')->nullable();

            // timestamps
            $table->timestamp('order_created_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // FK
            $table->foreign('shop_id')
                  ->references('id')
                  ->on('shops')
                  ->onDelete('cascade');

            // composite index (important for your query)
            $table->index(['shop_id', 'financial_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};