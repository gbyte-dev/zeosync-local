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
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('id')->index();
            // optional foreign key (commented out to avoid issues if shops table differs)
            // $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            // if foreign key was added, drop it first
            // $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
