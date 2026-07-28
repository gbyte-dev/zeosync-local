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
        // Change "create" to "table" here
        Schema::table('shopify_subscriptions', function (Blueprint $table) {
            $table->dateTime('activated_at')->nullable()->after('current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_subscriptions', function (Blueprint $table) {
            // Good practice: drop the column if you ever need to rollback
            $table->dropColumn('activated_at');
        });
    }
};