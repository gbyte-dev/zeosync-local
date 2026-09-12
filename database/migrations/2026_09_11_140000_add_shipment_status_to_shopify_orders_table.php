<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('shopify_orders', 'shipment_status')) {
                $table->string('shipment_status')->nullable()->after('fulfillment_status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_orders', 'shipment_status')) {
                $table->dropColumn('shipment_status');
            }
        });
    }
};
