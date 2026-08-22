<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'production_client_id'],
            [
                'option_value' => 'amzn1.application-oa2-client.83a9a7baa5ba4f0ba24c95e31592423d',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'production_client_secret'],
            [
                'option_value' => 'amzn1.oa2-cs.v1.8f657964fd68ad9d95ef333acf8f94441309bb2e80ed5be09a999225e6621946',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'amazon_refresh_token'],
            [
                'option_value' => 'Atzr|IwEBIPn3Sj-3OrYjbONKFz2WAwj_KAjCcWIzL8LXtj2i_-TT01LvdE35N6VYC2KLAK-hQX4ZnEpuFcXOBk3xYXYosT1YDLJ6nN2Punq13vXnv7v9ek3S8qGp1QbPKnvKHhI_XCtYj2kSS6NOQoCfqfAC__RYme6v5cCIb91uKWrT_d0TQVp8kwvJ88ooIPUggNXfCxrktuLluI3Mb-QFPUuZdhBYCqX3qfqmzufQpKbSe7PKDHIg-gL0DCUCFbazMRN9VqFlx09qc-tNnpLeigKJ-TtbRJ2CyX3GqGpk-6ySXrhXmWUftocaO1Fcpt72bIRxW9Y',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'amazon_seller_id'],
            [
                'option_value' => 'ATVPDKIKX0DER',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_api_key'],
            [
                'option_value' => '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',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'ai_provider'],
            [
                'option_value' => 'openai',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_model'],
            [
                'option_value' => 'gpt-4.1-mini',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_temperature'],
            [
                'option_value' => '0.2',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_endpoint'],
            [
                'option_value' => 'https://api.openai.com/v1/chat/completions',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'shopify_app_handle'],
            [
                'option_value' => 'zeosync',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
