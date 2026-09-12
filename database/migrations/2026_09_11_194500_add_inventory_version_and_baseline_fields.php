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
                if (!Schema::hasColumn('inventory_sync_operations', 'operation_uuid')) {
                    $table->string('operation_uuid')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'shopify_inventory_item_id')) {
                    $table->string('shopify_inventory_item_id')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'shopify_location_id')) {
                    $table->string('shopify_location_id')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'amazon_sku')) {
                    $table->string('amazon_sku')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'baseline_quantity')) {
                    $table->integer('baseline_quantity')->nullable()->after('desired_quantity');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'expected_inventory_version')) {
                    $table->unsignedBigInteger('expected_inventory_version')->default(1)->after('baseline_quantity');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'stage')) {
                    $table->string('stage')->default('pending');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'attempts')) {
                    $table->unsignedSmallInteger('attempts')->default(0);
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'max_attempts')) {
                    $table->unsignedSmallInteger('max_attempts')->default(4);
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'last_error')) {
                    $table->text('last_error')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'last_dispatched_at')) {
                    $table->timestamp('last_dispatched_at')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
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
