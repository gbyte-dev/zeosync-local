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
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('access_token_expires_at')
                ->nullable()
                ->after('access_token');

            $table->text('refresh_token')
                ->nullable()
                ->after('access_token_expires_at');

            $table->timestamp('refresh_token_expires_at')
                ->nullable()
                ->after('refresh_token');

            $table->string('shopify_connection_status')
                ->default('active')
                ->after('refresh_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'access_token_expires_at',
                'refresh_token',
                'refresh_token_expires_at',
                'shopify_connection_status',
            ]);
        });
    }
};
