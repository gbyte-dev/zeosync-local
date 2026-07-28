<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            $table->longText('smart_payload')
                ->nullable()
                ->after('intended_use');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_products', function (Blueprint $table) {
            $table->dropColumn('smart_payload');
        });
    }
};