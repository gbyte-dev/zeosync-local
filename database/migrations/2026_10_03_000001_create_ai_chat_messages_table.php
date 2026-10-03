<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id');
                $table->string('role', 20); // user, assistant, admin
                $table->text('message');
                $table->timestamps();

                $table->index(['shop_id', 'created_at']);
                $table->index(['shop_id', 'id']);
                $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
