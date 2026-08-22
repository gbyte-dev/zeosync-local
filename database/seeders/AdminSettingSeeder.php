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
                'option_value' => 'amzn1.application-oa2-client.0322f57a6ad749efa3a951ad9357c725',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'production_client_secret'],
            [
                'option_value' => 'amzn1.oa2-cs.v1.1971073ceebebcaf7a894b30f9a7871dacf4ba972990457f833ed37a7fe026ad',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('admin_settings')->updateOrInsert(
            ['option_key' => 'amazon_refresh_token'],
            [
                'option_value' => 'Atzr|IwEBIJnhRMfuiY42UbhRCT-vQh00_aEnEstun16SraOuXEf8Wo5YKGtlvgVysLibJjVkD_qCJu-YlBYjmmA3Bk6TpqlqxMB86umk9u0di7s3crsiZzPuKbfw_qf9TdK6vz_7-03fpWaVQDxWnHOLct3wOobhqlmjBJnapLIJN90QYeWJQFTxhd2uspWuapa9dHp19ESEH-Dfmje000jwL-g7mMVxXm-elB9ACsBQFfH1a08CPV67S8yAy8IE-Q2TFfC05orfDonq0hzMcivzt7Z3sijCL2UP_fQmSdM1wWqyWTw-DKidE6qep8nKE2ToRA_5Lhg',
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
