<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {

            if (! Schema::hasColumn('shop_subscriptions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('shopify_confirmation_url');
            }

            if (! Schema::hasColumn('shop_subscriptions', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('shop_subscriptions', 'current_period_end')) {
                $table->timestamp('current_period_end')->nullable()->after('trial_used');
            }

            if (! Schema::hasColumn('shop_subscriptions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('activated_at');
            }

            if (! Schema::hasColumn('shop_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('ended_at');
            }

            if (! Schema::hasColumn('shop_subscriptions', 'requested_plan_id')) {
                $table->integer('requested_plan_id')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_subscriptions', function (Blueprint $table) {

            foreach ([
                'started_at',
                'activated_at',
                'current_period_end',
                'ended_at',
                'cancelled_at',
                'requested_plan_id',
            ] as $column) {

                if (Schema::hasColumn('shop_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};