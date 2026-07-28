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
        Schema::create('product_schemas', function (Blueprint $table) {
                $table->id();
                $table->string('product_type')->unique();
                $table->string('schema_version')
                    ->nullable();
                $table->longText('schema_json');
                $table->longText('parsed_json')
                    ->nullable();
                $table->boolean('is_active')
                    ->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_schemas');

    }
};
