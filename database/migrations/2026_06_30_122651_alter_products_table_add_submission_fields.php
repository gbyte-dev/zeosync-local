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
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('parent_id');
            $table->string('submission_status')->nullable()->after('user_id');
            $table->timestamp('submitted_on')->nullable()->after('submission_status');
            $table->string('producttype')->nullable()->after('submitted_on');
            $table->longText('final_json')->nullable()->after('product_type');
            $table->longText('filled_json')->nullable()->after('final_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allproducts', function (Blueprint $table) {
             $table->dropColumn([
                'parent_id',
                'submission_status',
                'submitted_on',
                'user_id',
                'final_json',
                'filled_json',
                'producttype',
            ]);
        });
    }
};
