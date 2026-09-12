<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The real shops.access_token column was migrated as NOT NULL. A shop row
     * can legitimately exist before its Shopify access token is issued (and a
     * couple of feature tests create such rows), so relax it to nullable.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('access_token')->nullable(false)->change();
        });
    }
};