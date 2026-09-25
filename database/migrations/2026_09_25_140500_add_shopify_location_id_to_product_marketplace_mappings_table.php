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
        if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'shopify_location_id')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->string('shopify_location_id')->nullable()->after('shopify_inventory_item_id');
                $table->index(['shop_id', 'shopify_location_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_marketplace_mappings') && Schema::hasColumn('product_marketplace_mappings', 'shopify_location_id')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->dropIndex(['shop_id', 'shopify_location_id']);
                $table->dropColumn('shopify_location_id');
            });
        }
    }
};
