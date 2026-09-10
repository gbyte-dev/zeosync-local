<?php

namespace Tests\Feature;

use App\Services\Security\HtmlSanitizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class XssSecurityTest extends TestCase
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

        View::share('errors', new ViewErrorBag());
    }

    public function test_html_sanitizer_removes_executable_script_tags(): void
    {
        $input = '<p>Hello</p><script>alert(document.domain)</script><strong>World</strong>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert(document.domain)', $output);
        $this->assertStringContainsString('<p>Hello</p>', $output);
        $this->assertStringContainsString('<strong>World</strong>', $output);
    }

    public function test_html_sanitizer_removes_dangerous_event_handlers(): void
    {
        $input = '<img src="https://example.com/photo.jpg" onerror="alert(document.domain)" onload="alert(1)">';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('onload', $output);
        $this->assertStringNotContainsString('alert', $output);
        $this->assertStringContainsString('<img src="https://example.com/photo.jpg"', $output);
    }

    public function test_html_sanitizer_removes_javascript_pseudo_protocol_urls(): void
    {
        $input = '<a href="javascript:alert(document.domain)">Click Me</a>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringNotContainsString('javascript:', $output);
        $this->assertStringNotContainsString('alert', $output);
        $this->assertStringContainsString('Click Me', $output);
    }

    public function test_html_sanitizer_preserves_legitimate_rich_formatting(): void
    {
        $input = '<h3>Features</h3><p>Soft cotton fabric <em>(100%)</em></p><ul><li>Breathable</li><li>Durable</li></ul><a href="https://example.com" target="_blank">View Guide</a>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringContainsString('<h3>Features</h3>', $output);
        $this->assertStringContainsString('<p>Soft cotton fabric <em>(100%)</em></p>', $output);
        $this->assertStringContainsString('<ul><li>Breathable</li><li>Durable</li></ul>', $output);
        $this->assertStringContainsString('<a href="https://example.com"', $output);
        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
    }

    public function test_html_sanitizer_preserves_table_markup(): void
    {
        $input = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>M</td><td>38-40</td></tr></tbody></table>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringContainsString('<table>', $output);
        $this->assertStringContainsString('<thead><tr><th>Size</th><th>Chest</th></tr></thead>', $output);
        $this->assertStringContainsString('<tbody><tr><td>M</td><td>38-40</td></tr></tbody>', $output);
    }

    public function test_html_sanitizer_handles_quotes_apostrophes_and_entities_properly(): void
    {
        $input = '<p>Women\'s "Premium" T-Shirt &amp; Hoodie (10\\10 quality)</p>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringContainsString('Women\'s', $output);
        $this->assertStringContainsString('"Premium"', $output);
        $this->assertStringContainsString('T-Shirt', $output);
    }

    public function test_amazon_connect_success_view_safely_escapes_script_breakout(): void
    {
        $maliciousShop = '</script><script>alert("xss")</script>';
        $rendered = view('amazonconnect.success', ['shop' => $maliciousShop])->render();

        // Must not contain raw unescaped script breakout
        $this->assertStringNotContainsString('var shop = "</script>', $rendered);
        // Must contain safely escaped JSON (e.g. \u003C\/script\u003E)
        $this->assertStringContainsString('\u003C\/script\u003E', $rendered);
    }

    public function test_shopify_auth_popup_view_safely_escapes_script_breakout(): void
    {
        $maliciousShop = '</script><script>alert("xss")</script>';
        $maliciousUrl = 'https://example.com/oauth?param=</script><script>alert(1)</script>';

        $rendered = view('shopify.auth-popup', [
            'shop' => $maliciousShop,
            'redirectUrl' => $maliciousUrl,
        ])->render();

        $this->assertStringNotContainsString('const shop = "</script>', $rendered);
        $this->assertStringContainsString('\u003C\/script\u003E', $rendered);
    }

    public function test_shopify_auth_callback_view_safely_escapes_script_breakout(): void
    {
        $maliciousShop = '</script><script>alert("xss")</script>';
        $maliciousUrl = 'https://example.com/callback?data=</script>';

        $rendered = view('shopify.auth-callback', [
            'shop' => $maliciousShop,
            'redirectUrl' => $maliciousUrl,
        ])->render();

        $this->assertStringNotContainsString('shop: "</script>', $rendered);
        $this->assertStringContainsString('\u003C\/script\u003E', $rendered);
    }

    public function test_product_view_sanitizes_body_html_and_escapes_variant_scripts(): void
    {
        $product = [
            'id' => 12345,
            'title' => "Men's T-Shirt",
            'handle' => 'mens-t-shirt',
            'vendor' => 'Test Vendor',
            'product_type' => 'Apparel',
            'tags' => 'men, shirt',
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-02T00:00:00Z',
            'published_at' => '2026-01-01T00:00:00Z',
            'status' => 'active',
            'body_html' => '<p>Good product</p><script>alert(document.domain)</script><img src=x onerror=alert(1)>',
            'variants' => [
                [
                    'id' => 101,
                    'title' => 'Red / S',
                    'option1' => "Women's Red",
                    'option2' => 'S',
                    'option3' => null,
                    'price' => '29.99',
                    'sku' => 'TSHIRT-RED-S',
                    'inventory_quantity' => 5,
                ],
            ],
            'images' => [
                ['src' => 'https://example.com/img.jpg'],
            ],
        ];

        $rendered = view('product-view', [
            'product' => $product,
            'variants' => $product['variants'],
            'colors' => ["Women's Red"],
            'allSizes' => ['S'],
            'colorSizeMap' => ["Women's Red" => ['S']],
            'sizeColorsMap' => ['S' => ["Women's Red"]],
            'activeShop' => 'test-shop.myshopify.com',
        ])->render();

        // Ensure script and onerror are sanitized out of the body_html
        $this->assertStringNotContainsString('<script>alert(document.domain)</script>', $rendered);
        $this->assertStringNotContainsString('onerror=alert(1)', $rendered);
        $this->assertStringContainsString('<p>Good product</p>', $rendered);

        // Ensure JavaScript variables are serialized safely with JSON/hex encoding
        $this->assertStringContainsString('Women\u0027s Red', $rendered);
    }

    public function test_inventory_view_sanitizes_body_html_and_metafields(): void
    {
        $product = [
            'id' => 999,
            'title' => "Running Shoes",
            'vendor' => 'ShoeBrand',
            'product_type' => 'Footwear',
            'body_html' => '<p>High quality shoes</p><script>alert("xss")</script>',
            'variants' => [
                [
                    'id' => 201,
                    'title' => 'Blue / 10',
                    'option1' => 'Blue',
                    'option2' => '10',
                    'price' => '79.99',
                    'inventory_quantity' => 10,
                    'available' => 10,
                    'committed' => 0,
                    'on_hand' => 10,
                    'image_src' => 'https://example.com/blue.jpg',
                ],
            ],
            'metafields' => [
                'features' => 'Lightweight|Waterproof',
                'size_chart' => '<tr><td>10</td><td>28cm</td></tr><script>alert(1)</script>',
            ],
            'images' => [
                ['src' => 'https://example.com/blue.jpg'],
            ],
        ];

        $rendered = view('inventory.view', [
            'product' => $product,
            'activeShop' => 'test-shop.myshopify.com',
        ])->render();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $rendered);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('<p>High quality shoes</p>', $rendered);
        $this->assertStringContainsString('<tr><td>10</td><td>28cm</td></tr>', $rendered);
    }
}
