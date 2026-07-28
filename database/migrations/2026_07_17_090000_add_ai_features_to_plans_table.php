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
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('ai_autofill')
                ->default(false)
                ->after('product_limit');

            $table->boolean('ai_single_field')
                ->default(false)
                ->after('ai_autofill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'ai_autofill',
                'ai_single_field',
            ]);
        });
    }
};