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
        if (Schema::hasTable('inventory_sync_operations')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_sync_operations', 'source_key')) {
                    $table->string('source_key', 191)->nullable()->unique()->after('mapping_id');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'webhook_event_id')) {
                    $table->unsignedBigInteger('webhook_event_id')->nullable()->after('shop_id');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'sku')) {
                    $table->string('sku', 191)->default('')->after('source_key');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'source_state')) {
                    $table->string('source_state', 20)->default('pending')->after('source');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'inventory_item_id')) {
                    $table->string('inventory_item_id')->nullable()->after('source_state');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'location_id')) {
                    $table->string('location_id')->nullable()->after('inventory_item_id');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'marketplace_id')) {
                    $table->string('marketplace_id')->nullable()->after('location_id');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('marketplace_id');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'next_attempt_at')) {
                    $table->timestamp('next_attempt_at')->nullable()->index()->after('submitted_at');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'delta')) {
                    $table->integer('delta')->default(0)->after('next_attempt_at');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'requested_quantity')) {
                    $table->unsignedInteger('requested_quantity')->nullable()->after('delta');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'observed_quantity')) {
                    $table->unsignedInteger('observed_quantity')->nullable()->after('requested_quantity');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'error')) {
                    $table->text('error')->nullable()->after('max_attempts');
                }
                if (!Schema::hasColumn('inventory_sync_operations', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->after('completed_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventory_sync_operations')) {
            Schema::table('inventory_sync_operations', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_sync_operations', 'source_key')) {
                    $table->dropColumn('source_key');
                }
            });
        }
    }
};
