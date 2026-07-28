<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'amazon_schemas',
            function (Blueprint $table) {

                $table->longText('rules_json')
                    ->nullable()
                    ->after('schema_json');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'amazon_schemas',
            function (Blueprint $table) {

                $table->dropColumn(
                    'rules_json'
                );
            }
        );
    }
};