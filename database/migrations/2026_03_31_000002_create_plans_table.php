<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->text('prices')->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('stripe_price_ids')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('plans')->insert([
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 9.00,
                'badge' => 'Best for testing',
                'description' => 'A simple dummy Shopify subscription for testing app access.',
                'features' => json_encode([
                    'Sync up to 50 products',
                    'Basic Amazon listing support',
                    'Manual sync actions',
                    'Email support',
                ]),
                'is_highlighted' => false,
                'is_active' => true,
                'sort_order' => 1,
                'trial_days'  => 7,
                'stripe_price_ids' => json_encode([
                    'MONTHLY' => 'price_1234567890abcdef',
                    'ANNUAL' => 'price_1234567890abcdef',
                ]),
                'prices' => json_encode([
                    'EVERY_30_DAYS' => 9.00,
                    'ANNUAL' => 90.00,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'price' => 29.00,
                'badge' => 'Most popular',
                'description' => 'A balanced dummy plan for stores that need more volume and faster workflows.',
                'features' => json_encode([
                    'Sync up to 500 products',
                    'Bulk product actions',
                    'Priority sync queue',
                    'Advanced listing fields',
                ]),
                'is_highlighted' => true,
                'is_active' => true,
                'sort_order' => 2,
                'trial_days'  => 7,
                'stripe_price_ids' => json_encode([
                    'MONTHLY' => 'price_1234567890abcdef',
                    'ANNUAL' => 'price_1234567890abcdef',
                ]),
                'prices' => json_encode([
                    'EVERY_30_DAYS' => 29.00,
                    'ANNUAL' => 290.00,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Scale',
                'slug' => 'scale',
                'price' => 79.00,
                'badge' => 'For power users',
                'description' => 'A premium dummy plan for larger catalogs and team usage.',
                'features' => json_encode([
                    'Unlimited product sync',
                    'Multi-user access',
                    'Rule-based automation',
                    'Priority support',
                ]),
                'is_highlighted' => false,
                'is_active' => true,
                'sort_order' => 3,
                'trial_days'  => 7,
                'stripe_price_ids' => json_encode([
                    'MONTHLY' => 'price_1234567890abcdef',
                    'ANNUAL' => 'price_1234567890abcdef',
                ]),
                'prices' => json_encode([
                    'EVERY_30_DAYS' => 79.00,
                    'ANNUAL' => 790.00,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
