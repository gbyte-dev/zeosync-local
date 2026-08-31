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
                'option_value' => 'amzn1.application-test',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'production_client_secret'],
            [
                'option_value' => 'amzn1.oa2-cs.v1.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'amazon_refresh_token'],
            [
                'option_value' => 'Atzr|test',
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
                'option_value' => 'AQ.Ab8RN6Jyk9FrpQbZ6WX24eUi9JXrMw60dJTNLuL_1-grRVHL4w',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );  
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'ai_provider'],
            [
                'option_value' => 'gemini',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_model'],
            [
                'option_value' => 'gemini-3.5-flash-lite',
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
            ['option_key' => 'app_name'],
            [
                'option_value' => 'Zeosync',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'currency'],
            [
                'option_value' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        

        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'openai_endpoint'],
            [
                'option_value' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent',
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

        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'billing_provider'],
            [
                'option_value' => 'shopify',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
