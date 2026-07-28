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
        Schema::create('allproducts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schema_id')
                    ->constrained('product_schemas')
                    ->cascadeOnDelete();
                $table->string('sku')->unique();
                $table->string('status')
                    ->default('draft');
                $table->timestamps();
            });
        
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('attribute_name');
            $table->longText('attribute_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allproducts');
        Schema::dropIfExists('product_attributes');
    }
};
