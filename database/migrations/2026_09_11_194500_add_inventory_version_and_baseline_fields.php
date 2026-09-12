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
        if (Schema::hasTable('product_marketplace_mappings') && !Schema::hasColumn('product_marketplace_mappings', 'inventory_version')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->unsignedBigInteger('inventory_version')->default(1)->after('quantity');
            });
        }

        if (Schema::hasTable('inventory_sync_operations')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_sync_operations', 'baseline_quantity')) {
                    $table->integer('baseline_quantity')->nullable()->after('desired_quantity');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'expected_inventory_version')) {
                    $table->unsignedBigInteger('expected_inventory_version')->default(1)->after('baseline_quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_marketplace_mappings') && Schema::hasColumn('product_marketplace_mappings', 'inventory_version')) {
            Schema::table('product_marketplace_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_version');
            });
        }

        if (Schema::hasTable('inventory_sync_operations')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_sync_operations', 'expected_inventory_version')) {
                    $table->dropColumn('expected_inventory_version');
                }
                if (Schema::hasColumn('inventory_sync_operations', 'baseline_quantity')) {
                    $table->dropColumn('baseline_quantity');
                }
            });
        }
    }
};
