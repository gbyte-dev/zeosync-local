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
        Schema::table('product_marketplace_mappings', function (Blueprint $table) {
            $table->unique(
                ['shop_id', 'shopify_variant_id'],
                'unique_shop_shopify_variant'
            );

            $table->unique(
                ['shop_id', 'amazon_sku'],
                'unique_shop_amazon_sku'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_marketplace_mappings', function (Blueprint $table) {
            $table->dropUnique('unique_shop_shopify_variant');
            $table->dropUnique('unique_shop_amazon_sku');
        });
    }
};
