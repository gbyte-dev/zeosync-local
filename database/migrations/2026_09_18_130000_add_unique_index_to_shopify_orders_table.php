<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->unique(
                ['shop_id', 'shopify_order_id'],
                'shopify_orders_shop_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropUnique('shopify_orders_shop_order_unique');
        });
    }
};
