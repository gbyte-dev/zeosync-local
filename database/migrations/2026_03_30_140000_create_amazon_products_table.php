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
        Schema::create('amazon_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->onDelete('cascade');
            $table->string('amazon_title')->nullable();
            $table->json('search_terms')->nullable();
            $table->json('platinum_keywords')->nullable();
            $table->json('bullet_points')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('subject_matter')->nullable();
            $table->string('sku')->nullable();
            $table->json('intended_use')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_products');
    }
};
