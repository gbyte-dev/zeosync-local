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
        if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'fulfillment_channel_code')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->string('fulfillment_channel_code')->nullable()->after('amazon_product_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_marketplace_mappings') && Schema::hasColumn('product_marketplace_mappings', 'fulfillment_channel_code')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->dropColumn('fulfillment_channel_code');
            });
        }
    }
};
