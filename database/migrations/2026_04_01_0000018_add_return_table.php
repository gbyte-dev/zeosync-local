<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                  ->constrained()
                  ->cascadeOnDelete();
        
            $table->string('order_id');
            $table->string('product_name')->nullable();
            $table->enum('status', ['requested', 'approved', 'refunded'])
                  ->default('requested');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
        
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
