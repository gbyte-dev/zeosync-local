<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('shops','selected_location_id')) {
            Schema::table('shops', function (Blueprint $t) {
                $t->string('selected_location_id')->nullable()->after('selected_location_index');
            });
        }
        if (!Schema::hasTable('shopify_order_items')) {
            Schema::create('shopify_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('shop_id');
                $t->unsignedBigInteger('shopify_order_id');
                $t->string('source_line_key',96);
                $t->string('shopify_product_id')->nullable();
                $t->string('shopify_variant_id')->nullable();
                $t->string('sku')->nullable();
                $t->string('title')->nullable();
                $t->unsignedInteger('quantity')->default(0);
                $t->decimal('unit_price',14,4)->default(0);
                $t->decimal('line_total',14,4)->default(0);
                $t->timestamp('order_created_at')->nullable();
                $t->timestamps();
                $t->unique(['shop_id','shopify_order_id','source_line_key'],'shop_order_line_unique');
                $t->index(['shop_id','order_created_at']);
                $t->index(['shop_id','shopify_product_id']);
                $t->index(['shop_id','shopify_variant_id']);
                $t->index(['shop_id','sku']);
                $t->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
                $t->foreign('shopify_order_id')->references('id')->on('shopify_orders')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('billing_reconciliations')) {
            Schema::create('billing_reconciliations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $t->string('action',100);
                $t->string('status',40)->default('open')->index();
                $t->string('provider_reference',191)->nullable()->index();
                $t->text('error')->nullable();
                $t->json('context')->nullable();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('shop_notification_preferences')) {
            Schema::create('shop_notification_preferences', function (Blueprint $t) {
                $t->id();
                $t->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $t->string('notification_key',100);
                $t->boolean('app_enabled')->default(true);
                $t->boolean('mail_enabled')->default(true);
                $t->timestamps();
                $t->unique(['shop_id','notification_key'],'shop_notification_preferences_unique');
            });
        }
        Schema::table('plans', function (Blueprint $t) {
            if (!Schema::hasColumn('plans','commercial_basis')) {
                $t->text('commercial_basis')->nullable();
            }
        });
    }
    public function down(): void {
        Schema::dropIfExists('shop_notification_preferences');
        Schema::dropIfExists('billing_reconciliations');
        Schema::dropIfExists('shopify_order_items');
    }
};
