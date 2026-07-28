<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->boolean('auto_sync')->default(false);
            $table->boolean('ai_assist')->default(false);
            $table->string('currency', 3)->default('USD');
            $table->string('tax_behavior')->default('exclude'); // include/exclude
            $table->string('ai_client_id')->nullable();
            $table->string('ai_client_secret')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};