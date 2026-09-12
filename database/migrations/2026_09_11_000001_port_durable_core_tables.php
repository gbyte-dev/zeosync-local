<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $t) {
                $t->id();
                $t->string('provider',32);
                $t->string('event_id',191);
                $t->string('topic',191)->nullable();
                $t->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $t->string('status',32)->default('received');
                $t->unsignedInteger('attempts')->default(0);
                $t->longText('payload')->nullable();
                $t->text('error')->nullable();
                $t->timestamp('received_at')->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->timestamps();
                $t->unique(['provider','event_id']);
                $t->index(['shop_id','provider','status']);
            });
        }
        if (!Schema::hasTable('inventory_sync_operations')) {
            Schema::create('inventory_sync_operations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $t->unsignedBigInteger('webhook_event_id')->nullable();
                $t->unsignedBigInteger('mapping_id')->nullable();
                $t->string('source_key',191)->unique();
                $t->string('sku',191)->default('');
                $t->string('source',32)->default('manual_shopify');
                $t->string('source_state',20)->default('pending');
                $t->string('inventory_item_id')->nullable();
                $t->string('location_id')->nullable();
                $t->string('marketplace_id')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('next_attempt_at')->nullable()->index();
                $t->integer('delta')->default(0);
                $t->unsignedInteger('requested_quantity')->nullable();
                $t->unsignedInteger('observed_quantity')->nullable();
                $t->integer('desired_quantity')->default(0);
                $t->string('status',32)->default('pending');
                $t->unsignedInteger('attempts')->default(0);
                $t->text('error')->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->timestamps();
                $t->index(['shop_id','sku','status']);
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('inventory_sync_operations');
        Schema::dropIfExists('webhook_events');
    }
};
