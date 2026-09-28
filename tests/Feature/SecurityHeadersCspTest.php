<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SecurityHeadersCspTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('admin_settings')) {
            Schema::create('admin_settings', function (Blueprint $table) {
                $table->id();
                $table->string('option_key')->unique();
                $table->text('option_value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $table) {
                $table->id();
                $table->string('shop')->unique();
                $table->string('shop_name')->nullable();
                $table->string('email')->nullable();
                $table->text('access_token')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('shopify_locations')->nullable();
                $table->integer('selected_location_index')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        AdminSetting::updateOrCreate(
            ['option_key' => 'SHOPIFY_API_SECRET'],
            ['option_value' => 'test_api_secret_key_1234567890123456']
        );
        AdminSetting::updateOrCreate(
            ['option_key' => 'SHOPIFY_API_KEY'],
            ['option_value' => 'test_api_client_key_1234567890123456']
        );

        View::share('errors', new ViewErrorBag());
        View::share('cspNonce', 'test-csp-nonce');
    }

    public function test_embedded_merchant_dashboard_receives_shopify_frame_ancestors(): void
    {
        $shop = Shop::create([
            'shop' => 'test-store.myshopify.com',
            'shop_name' => 'Test Store',
            'email' => 'merchant@test.com',
            'access_token' => 'shpat_test_token_12345',
            'is_active' => true,
        ]);

        $response = $this->withSession([
            '_shopify_verified_shop' => $shop->shop,
            'active_shop' => $shop->shop,
            'active_shop_id' => $shop->id,
        ])->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_document_navigation_inside_embedded_shopify_receives_shopify_frame_ancestors(): void
    {
        // This is the critical regression test for Sec-Fetch-Dest === 'document' (in-frame link click)
        $shop = Shop::create([
            'shop' => 'test-store-2.myshopify.com',
            'shop_name' => 'Test Store 2',
            'email' => 'merchant2@test.com',
            'access_token' => 'shpat_test_token_12345',
            'is_active' => true,
        ]);

        $response = $this->withSession([
            '_shopify_verified_shop' => $shop->shop,
            'active_shop' => $shop->shop,
            'active_shop_id' => $shop->id,
        ])->withHeaders([
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
        ])->get('/plans');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com", $csp);
    }

    public function test_embedded_internal_routes_receive_shopify_frame_ancestors_without_fetch_metadata(): void
    {
        $shop = Shop::create([
            'shop' => 'test-store-3.myshopify.com',
            'shop_name' => 'Test Store 3',
            'email' => 'merchant3@test.com',
            'access_token' => 'shpat_test_token_12345',
            'is_active' => true,
        ]);

        $routesToTest = [
            '/settings',
            '/inventory/shopify',
            '/connect',
            '/help',
            '/support',
            '/notification',
        ];

        foreach ($routesToTest as $uri) {
            $response = $this->withSession([
                '_shopify_verified_shop' => $shop->shop,
                'active_shop' => $shop->shop,
                'active_shop_id' => $shop->id,
            ])->get($uri);

            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertNotNull($csp, "CSP missing on {$uri}");
            $this->assertStringContainsString("frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com", $csp, "Shopify frame-ancestors missing on {$uri}");
        }
    }

    public function test_standalone_public_pages_do_not_receive_shopify_frame_ancestors(): void
    {
        $publicRoutes = [
            '/',
            '/about',
            '/pricing',
            '/contact',
            '/terms',
            '/privacy',
        ];

        foreach ($publicRoutes as $uri) {
            $response = $this->get($uri);

            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertNotNull($csp, "CSP missing on public route {$uri}");
            $this->assertStringNotContainsString('https://admin.shopify.com', $csp, "Public route {$uri} must not grant Shopify framing permission");
            $this->assertStringNotContainsString('https://*.myshopify.com', $csp, "Public route {$uri} must not grant Shopify framing permission");
            $this->assertStringContainsString("frame-ancestors 'self'", $csp, "Public route {$uri} should retain restrictive frame-ancestors 'self'");
        }
    }

    public function test_crm_admin_routes_do_not_receive_shopify_frame_ancestors(): void
    {
        $adminRoutes = [
            '/admin/login',
            '/admin',
            '/admin/dashboard',
            '/admin/shops',
        ];

        foreach ($adminRoutes as $uri) {
            // Even if an attacker passes Sec-Fetch-Dest: iframe or Shopify host/embedded query params,
            // /admin routes MUST NOT receive Shopify framing permissions.
            $response = $this->withHeaders([
                'Sec-Fetch-Dest' => 'iframe',
                'Referer' => 'https://admin.shopify.com',
            ])->get($uri . '?embedded=1&host=YWRtaW4uc2hvcGlmeS5jb20=');

            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertNotNull($csp, "CSP missing on admin route {$uri}");
            $this->assertStringNotContainsString('https://admin.shopify.com', $csp, "Admin route {$uri} must NOT be frameable by Shopify");
            $this->assertStringNotContainsString('https://*.myshopify.com', $csp, "Admin route {$uri} must NOT be frameable by Shopify");
            $this->assertStringContainsString("frame-ancestors 'self'", $csp, "Admin route {$uri} must have restrictive frame-ancestors 'self'");
        }
    }

    public function test_embedded_install_breakout_receives_shopify_frame_ancestors(): void
    {
        $response = $this->get('/install?shop=test-install.myshopify.com&embedded=1');

        $this->assertEquals(200, $response->getStatusCode());
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com", $csp);
    }

    public function test_standalone_install_redirects_with_security_headers(): void
    {
        $response = $this->get('/install?shop=test-install.myshopify.com');

        $this->assertTrue($response->isRedirection());
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_existing_standard_security_headers_are_consistently_present(): void
    {
        $response = $this->get('/');

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertEquals('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }
}
