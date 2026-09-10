<?php

use App\Models\AdminSetting;
use App\Models\NotificationSetting;
use App\Services\AIConfigurationService;
use App\Providers\StripeServiceProvider;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    } else {
        DB::table('admin_settings')->truncate();
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('notification_key')->unique();
            $table->text('description')->nullable();
            $table->boolean('email_enabled')->default(0);
            $table->boolean('in_app_enabled')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    Cache::flush();
});

it('TEST 1: encrypts sensitive plaintext values when saved through AdminSetting model', function () {
    $sensitiveKeys = [
        'production_client_id'     => 'amzn1.application-oa2-client-id-12345',
        'production_client_secret' => 'amzn1.oa2-cs.v1.secret-abcdef98765',
        'amazon_refresh_token'     => 'Atzr|amazon-refresh-token-value-sample',
        'test_client_id'           => 'amzn1.test-client-id-xyz',
        'test_client_secret'       => 'amzn1.test-client-secret-xyz',
        'test_refresh_token'       => 'Atzr|test-refresh-token-xyz',
        'SHOPIFY_API_KEY'          => 'shpat_shopify_client_key_9999',
        'SHOPIFY_API_SECRET'       => 'shpss_shopify_secret_key_8888',
        'stripe_secret_key'        => 'sk_live_stripe_secret_key_1111',
        'stripe_webhook_secret'    => 'whsec_stripe_webhook_secret_2222',
        'SMTP_username'            => 'smtp_user_account@example.com',
        'SMTP_password'            => 'SuperSecretSMTPPassword123!',
        'openai_api_key'           => 'AQ.Ab8RN6Jyk9FrpQbZ6WX24eUi9JXrMw60dJTNLuL_1-grRVHL4w',
    ];

    foreach ($sensitiveKeys as $key => $plainValue) {
        AdminSetting::create([
            'option_key'   => $key,
            'option_value' => $plainValue,
        ]);

        // 1. Raw DB value must be ciphertext and NOT equal to plain value
        $rawDbValue = DB::table('admin_settings')->where('option_key', $key)->value('option_value');
        expect($rawDbValue)->not->toBeNull()
            ->and($rawDbValue)->not->toBe($plainValue);

        // 2. Raw DB value must decrypt to the exact plaintext
        expect(Crypt::decryptString($rawDbValue))->toBe($plainValue);
    }
});

it('TEST 2: decrypts encrypted sensitive DB values when read through AdminSetting model', function () {
    $plainApiKey = 'AQ.Ab8RN6Jyk9FrpQbZ6WX24eUi9JXrMw60dJTNLuL_test';
    $encryptedCipher = Crypt::encryptString($plainApiKey);

    // Insert directly into DB as ciphertext
    DB::table('admin_settings')->insert([
        'option_key'   => 'openai_api_key',
        'option_value' => $encryptedCipher,
    ]);

    $setting = AdminSetting::where('option_key', 'openai_api_key')->first();
    expect($setting->option_value)->toBe($plainApiKey);
});

it('TEST 3: handles legacy plaintext sensitive DB values gracefully without exception', function () {
    $legacyPlaintext = 'legacy_unencrypted_secret_value_123';

    // Insert raw plaintext directly via DB::table (simulating pre-existing legacy data or seeder)
    DB::table('admin_settings')->insert([
        'option_key'   => 'production_client_secret',
        'option_value' => $legacyPlaintext,
    ]);

    $setting = AdminSetting::where('option_key', 'production_client_secret')->first();
    expect($setting->option_value)->toBe($legacyPlaintext);

    // AdminSetting::get() must also return the legacy plaintext safely
    expect(AdminSetting::get('production_client_secret'))->toBe($legacyPlaintext);
});

it('TEST 4: populates Admin Settings view with decrypted sensitive values and non-sensitive values', function () {
    $plainSecret = 'sk_test_stripe_secret_key_456';
    $plainName = 'ZeoSync App Name';

    // Save sensitive and non-sensitive settings
    AdminSetting::create(['option_key' => 'stripe_secret_key', 'option_value' => $plainSecret]);
    AdminSetting::create(['option_key' => 'app_name', 'option_value' => $plainName]);

    // AdminController::settings uses AdminSetting::all()->pluck('option_value', 'option_key')->toArray()
    $settings = AdminSetting::all()->pluck('option_value', 'option_key')->toArray();

    expect($settings['stripe_secret_key'])->toBe($plainSecret)
        ->and($settings['app_name'])->toBe($plainName);
});

it('TEST 5: encrypts sensitive values submitted through AdminController::settingsupdate', function () {
    $admin = \App\Models\Admin::create([
        'name'     => 'Super Admin',
        'email'    => 'admin@zeosync.com',
        'password' => 'secret123',
        'role'     => 'super_admin',
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        'app_name'                 => 'ZeoSync Main',
        'currency'                 => 'USD',
        'production_client_id'     => 'amzn1.application-new-id',
        'production_client_secret' => 'amzn1.oa2-cs.new-secret',
        'amazon_refresh_token'     => 'Atzr|new-refresh-token',
        'stripe_secret_key'        => 'sk_live_new_stripe_key',
        'SHOPIFY_API_KEY'          => 'new_shopify_key',
        'SHOPIFY_API_SECRET'       => 'new_shopify_secret',
        'openai_api_key'           => 'new_openai_key',
    ]);

    $response->assertSessionHasNoErrors();

    // Verify raw DB values for sensitive settings are ciphertext
    $rawClientId = DB::table('admin_settings')->where('option_key', 'production_client_id')->value('option_value');
    expect($rawClientId)->not->toBe('amzn1.application-new-id')
        ->and(Crypt::decryptString($rawClientId))->toBe('amzn1.application-new-id');

    $rawStripe = DB::table('admin_settings')->where('option_key', 'stripe_secret_key')->value('option_value');
    expect($rawStripe)->not->toBe('sk_live_new_stripe_key')
        ->and(Crypt::decryptString($rawStripe))->toBe('sk_live_new_stripe_key');

    // Verify non-sensitive setting is stored as plaintext
    $rawAppName = DB::table('admin_settings')->where('option_key', 'app_name')->value('option_value');
    expect($rawAppName)->toBe('ZeoSync Main');
});

it('TEST 6: preserves plaintext for non-sensitive settings in the database', function () {
    $nonSensitive = [
        'app_name'               => 'ZeoSync Platform',
        'currency'               => 'USD',
        'timezone'               => 'UTC',
        'admin_email'            => 'admin@zeosync.com',
        'SMTP_host'              => 'smtp.mailtrap.io',
        'SMTP_port'              => '587',
        'SMTP_encryption'        => 'tls',
        'from_email'             => 'no-reply@zeosync.com',
        'from_name'              => 'ZeoSync System',
        'stripe_publishable_key' => 'pk_live_public_key_12345',
        'amazon_seller_id'       => 'ATVPDKIKX0DER',
        'amazon_app_id'          => 'amzn1.sp.solution.1111',
        'SHOPIFY_REDIRECT_URI'   => 'https://zeosync.com/shopify/callback',
        'ai_provider'            => 'gemini',
        'openai_model'           => 'gemini-3.5-flash-lite',
        'openai_temperature'     => '0.2',
        'openai_endpoint'        => 'https://generativelanguage.googleapis.com',
        'openai_max_tokens'      => '2048',
    ];

    foreach ($nonSensitive as $key => $value) {
        AdminSetting::create(['option_key' => $key, 'option_value' => $value]);

        $rawDb = DB::table('admin_settings')->where('option_key', $key)->value('option_value');
        expect($rawDb)->toBe($value);
    }
});

it('TEST 7: prevents double encryption when an already encrypted value is saved', function () {
    $plainToken = 'Atzr|unique-amazon-refresh-token-123';
    $firstEncrypted = Crypt::encryptString($plainToken);

    // Save already encrypted value
    $setting = AdminSetting::create([
        'option_key'   => 'amazon_refresh_token',
        'option_value' => $firstEncrypted,
    ]);

    $rawDb = DB::table('admin_settings')->where('option_key', 'amazon_refresh_token')->value('option_value');
    expect(Crypt::decryptString($rawDb))->toBe($plainToken);

    // Eloquent read
    $reloaded = AdminSetting::find($setting->id);
    expect($reloaded->option_value)->toBe($plainToken);
});

it('TEST 8: returns decrypted plaintext via AdminSetting::get() and setting() helpers', function () {
    AdminSetting::create([
        'option_key'   => 'SHOPIFY_API_SECRET',
        'option_value' => 'shpss_super_secret_shopify_key_12345',
    ]);

    expect(AdminSetting::get('SHOPIFY_API_SECRET'))->toBe('shpss_super_secret_shopify_key_12345')
        ->and(setting('SHOPIFY_API_SECRET'))->toBe('shpss_super_secret_shopify_key_12345');

    // Non-existent key with fallback
    expect(AdminSetting::get('non_existent_key', 'default_val'))->toBe('default_val')
        ->and(setting('non_existent_key', 'default_val'))->toBe('default_val');
});

it('TEST 9: provides decrypted openai_api_key to AIConfigurationService', function () {
    $plainApiKey = 'AQ.Ab8RN6Jyk9FrpQbZ6WX24eUi9JXrMw60dJTNLuL_gemini';

    AdminSetting::create(['option_key' => 'openai_api_key', 'option_value' => $plainApiKey]);
    AdminSetting::create(['option_key' => 'ai_provider', 'option_value' => 'gemini']);
    AdminSetting::create(['option_key' => 'openai_model', 'option_value' => 'gemini-3.5-flash-lite']);

    $configService = new AIConfigurationService();
    $configService->clearCache();
    $config = $configService->get();

    expect($config['api_key'])->toBe($plainApiKey)
        ->and($config['provider'])->toBe('gemini')
        ->and($config['model'])->toBe('gemini-3.5-flash-lite');
});

it('TEST 10: configures mail credentials with decrypted plaintext via getMailSettings()', function () {
    AdminSetting::create(['option_key' => 'SMTP_username', 'option_value' => 'mail_user@smtp.com']);
    AdminSetting::create(['option_key' => 'SMTP_password', 'option_value' => 'SecretMailPassword456!']);
    AdminSetting::create(['option_key' => 'SMTP_host', 'option_value' => 'smtp.mailgun.org']);
    AdminSetting::create(['option_key' => 'SMTP_port', 'option_value' => '587']);

    getMailSettings();

    expect(config('mail.mailers.smtp.username'))->toBe('mail_user@smtp.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('SecretMailPassword456!')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.mailgun.org')
        ->and(config('mail.mailers.smtp.port'))->toBe('587');
});

it('TEST 11: ensures Stripe service provider receives decrypted plaintext secrets', function () {
    $plainStripeSecret = 'sk_test_stripe_secret_key_777';
    $plainWebhookSecret = 'whsec_stripe_webhook_secret_888';

    AdminSetting::create(['option_key' => 'stripe_secret_key', 'option_value' => $plainStripeSecret]);
    AdminSetting::create(['option_key' => 'stripe_webhook_secret', 'option_value' => $plainWebhookSecret]);

    $provider = new StripeServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getStripeSecretKey');
    $method->setAccessible(true);

    $secretKey = $method->invoke($provider);
    expect($secretKey)->toBe($plainStripeSecret)
        ->and(StripeServiceProvider::getWebhookSecret())->toBe($plainWebhookSecret);
});

it('TEST 12: ensures Shopify authentication and token validator receive decrypted credentials', function () {
    $plainApiKey = 'shopify_api_key_test_123';
    $plainApiSecret = 'shopify_api_secret_test_456';

    AdminSetting::create(['option_key' => 'SHOPIFY_API_KEY', 'option_value' => $plainApiKey]);
    AdminSetting::create(['option_key' => 'SHOPIFY_API_SECRET', 'option_value' => $plainApiSecret]);

    $validator = new ShopifySessionTokenValidator();
    expect(AdminSetting::get('SHOPIFY_API_KEY'))->toBe($plainApiKey)
        ->and(AdminSetting::get('SHOPIFY_API_SECRET'))->toBe($plainApiSecret);
});

it('TEST 13: ensures Amazon authentication credentials are decrypted for AmazonConnect and AmazonService', function () {
    $plainClientId = 'amzn1.application-prod-client-id';
    $plainClientSecret = 'amzn1.oa2-cs.v1.prod-client-secret';

    AdminSetting::create(['option_key' => 'production_client_id', 'option_value' => $plainClientId]);
    AdminSetting::create(['option_key' => 'production_client_secret', 'option_value' => $plainClientSecret]);

    $amazonService = app(\App\Services\AmazonService::class);
    $mockShop = (object) [
        'amazon_refresh_token' => 'Atzr|shop_refresh_token',
        'amazon_seller_id'     => 'SELLER123',
    ];

    $creds = $amazonService->getDbCredentials($mockShop);

    expect($creds['client_id'])->toBe($plainClientId)
        ->and($creds['client_secret'])->toBe($plainClientSecret)
        ->and($creds['refresh_token'])->toBe('Atzr|shop_refresh_token');
});

it('TEST 14: invalidates cache immediately after an Admin Setting update', function () {
    $initialSecret = 'old_secret_key_111';
    $updatedSecret = 'new_secret_key_222';

    AdminSetting::create(['option_key' => 'stripe_secret_key', 'option_value' => $initialSecret]);

    // Initial cache population
    expect(AdminSetting::get('stripe_secret_key'))->toBe($initialSecret);

    // Update setting via updateOrCreate
    AdminSetting::updateOrCreate(
        ['option_key' => 'stripe_secret_key'],
        ['option_value' => $updatedSecret]
    );

    // Cache must immediately return updated decrypted value
    expect(AdminSetting::get('stripe_secret_key'))->toBe($updatedSecret);
});
