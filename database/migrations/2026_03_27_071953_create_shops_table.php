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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->text('access_token');
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->string('domain')->nullable();
            $table->string('plan')->nullable();
            $table->string('plan_expires_at')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->default('na'); // na, eu, fe
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_endpoint')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('hmac')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
