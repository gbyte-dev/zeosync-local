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
        $hasShopifyStatus = Schema::hasColumn('products', 'shopify_status');
        $hasShopifyError = Schema::hasColumn('products', 'shopify_error');

        if ($hasShopifyStatus && $hasShopifyError) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($hasShopifyStatus, $hasShopifyError) {
            if (!$hasShopifyStatus) {
                $table->string('shopify_status')->default('pending')->after('status');
            }

            if (!$hasShopifyError) {
                $table->text('shopify_error')->nullable()->after('shopify_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasShopifyStatus = Schema::hasColumn('products', 'shopify_status');
        $hasShopifyError = Schema::hasColumn('products', 'shopify_error');

        if (!$hasShopifyStatus && !$hasShopifyError) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($hasShopifyStatus, $hasShopifyError) {
            if ($hasShopifyError) {
                $table->dropColumn('shopify_error');
            }

            if ($hasShopifyStatus) {
                $table->dropColumn('shopify_status');
            }
        });
    }
};
