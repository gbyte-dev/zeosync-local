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
        Schema::table('allproducts', function (Blueprint $table) {
            $table->dropUnique('allproducts_sku_unique');
            $table->unique(['user_id', 'sku'], 'allproducts_user_id_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allproducts', function (Blueprint $table) {
            $table->dropUnique('allproducts_user_id_sku_unique');
            $table->unique('sku', 'allproducts_sku_unique');
        });
    }
};
