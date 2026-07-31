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
        if (!Schema::hasColumn('shop_subscriptions', 'requested_plan_id')) {
            Schema::table('shop_subscriptions', function (Blueprint $table) {
                $table->integer('requested_plan_id')
                    ->nullable()
                    ->after('updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('shop_subscriptions', 'requested_plan_id')) {
            Schema::table('shop_subscriptions', function (Blueprint $table) {
                $table->dropColumn('requested_plan_id');
            });
        }
    }
};