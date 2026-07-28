<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_subscriptions', 'billing_cycle_months')) {
                $table->unsignedInteger('billing_cycle_months')->default(1)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('shop_subscriptions', 'billing_cycle_months')) {
                $table->dropColumn('billing_cycle_months');
            }
        });
    }
};
