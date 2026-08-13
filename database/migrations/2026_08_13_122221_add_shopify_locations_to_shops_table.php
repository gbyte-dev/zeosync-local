<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('shopify_locations')
                ->nullable()
                ->after('access_token');

            $table->unsignedInteger('selected_location_index')
                ->nullable()
                ->after('shopify_locations');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_locations',
                'selected_location_index',
            ]);
        });
    }
};