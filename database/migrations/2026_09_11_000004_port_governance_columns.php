<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('plans', function (Blueprint $t) {
            if (!Schema::hasColumn('plans','approved_by_admin_id')) {
                $t->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            }
            if (!Schema::hasColumn('plans','approved_at')) {
                $t->timestamp('approved_at')->nullable();
            }
        });
        Schema::table('shopify_subscriptions', function (Blueprint $t) {
            if (!Schema::hasColumn('shopify_subscriptions','last_stripe_event_at')) {
                $t->unsignedBigInteger('last_stripe_event_at')->default(0);
            }
            if (!Schema::hasColumn('shopify_subscriptions','last_stripe_event_id')) {
                $t->string('last_stripe_event_id')->nullable();
            }
        });
    }
    public function down(): void {
    }
};
