<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Services\AIConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function __construct(
        private readonly AIConfigurationService $configService
    ) {
    }

    public function index(Request $request)
    {
        if ($request->query('reset')) {
            $request->session()->forget('ai_chat_history');
            return redirect()->route('shopify.ai.chat', ['shop' => $request->query('shop')]);
        }

        $chatHistory = $request->session()->get('ai_chat_history', []);

        return view('aichat.index', [
            'chatHistory' => $chatHistory,
            'currentShop' => $request->query('shop') ?? session('active_shop'),
        ]);
    }

    public function ask(Request $request)
    {
        $request->validate([
            'prompt' => ['required', 'string', 'max:1200'],
        ]);

        $shop = $this->resolveShop();
        $context = $this->buildShopContext($shop);
        $prompt = $request->input('prompt');

        $systemPrompt = "You are AmazonSync AI. Answer user questions about the current Shopify store, product performance, Amazon order cache, and marketplace pricing based on the data provided below. Be concise and factual. If exact data is unavailable, say so clearly. If the user asks for product details, provide the exact product detail link from the store context.";
        $userPrompt = "Store context:\n{$context}\n\nUser question: {$prompt}";

        $response = $this->sendAiRequest($systemPrompt, $userPrompt);

        $history = $request->session()->get('ai_chat_history', []);
        $history[] = [
            'role' => 'user',
            'message' => $prompt,
        ];

        if ($response['success']) {
            $history[] = [
                'role' => 'assistant',
                'message' => $response['message'],
            ];
            $request->session()->put('ai_chat_history', $history);
            return redirect()->route('shopify.ai.chat', ['shop' => $request->query('shop')]);
        }

        Log::error('AI chat error', ['error' => $response['error']]);

        return back()
            ->withErrors(['prompt' => $response['error']])
            ->withInput();
    }

    private function resolveShop(): ?Shop
    {
        $shopHandle = session('active_shop');
        if (empty($shopHandle)) {
            return Shop::first();
        }

        return Shop::where('shop', $shopHandle)->first() ?? Shop::first();
    }

    private function buildShopContext(?Shop $shop): string
    {
        if (!$shop) {
            return 'No active shop is available.';
        }

        $products = Product::where('shop_id', $shop->id)
            ->orderBy('title')
            ->get(['title', 'price', 'amazon_product_id', 'shopify_id']);

        $orders = ShopifyOrder::where('shop_id', $shop->id)
            ->whereNull('cancelled_at')
            ->get(['line_items']);

        $salesByProduct = [];
        foreach ($orders as $order) {
            foreach ($order->line_items ?? [] as $item) {
                $title = trim((string) data_get($item, 'title', 'Unknown Product'));
                $quantity = (int) data_get($item, 'quantity', 1);
                $salesByProduct[$title] = ($salesByProduct[$title] ?? 0) + $quantity;
            }
        }

        arsort($salesByProduct);
        $topProducts = array_slice($salesByProduct, 0, 5, true);

        $amazonOrders = $this->getAmazonOrdersCache($shop);
        $amazonInventory = $this->getAmazonInventoryCache($shop);

        $productLines = [];
        foreach ($products as $product) {
            $price = $product->price !== null ? number_format($product->price, 2) : 'N/A';
            $shopifyLink = $product->shopify_id ? route('shopify.product.view', ['id' => $product->shopify_id, 'shop' => $shop->shop]) : 'Not available';
            $amazonLink = $product->amazon_product_id ? route('user.product.amazonView', ['sku' => $product->amazon_product_id, 'shop' => $shop->shop]) : 'Not available';
            $amazonId = $product->amazon_product_id ? $product->amazon_product_id : 'Not available';

            $productLines[] = "{$product->title} | Shopify price: ".($price === 'N/A' ? $price : '$'.$price)." | Amazon SKU: {$amazonId} | Shopify details: {$shopifyLink} | Amazon details: {$amazonLink}";
        }

        $topProductsLines = [];
        foreach ($topProducts as $title => $quantity) {
            $topProductsLines[] = "{$title} sold {$quantity} units";
        }

        $amazonOrderIds = collect($amazonOrders)
            ->pluck('AmazonOrderId')
            ->filter()
            ->take(10)
            ->values()
            ->all();

        return implode("\n", array_filter([
            "Shop name: {$shop->shop}",
            'Total Shopify products: ' . count($products),
            'Top selling products: ' . ($topProductsLines ? implode('; ', $topProductsLines) : 'No sales data available'),
            'Amazon  orders: ' . count($amazonOrders) . ' orders' . ($amazonOrderIds ? ' (sample IDs: ' . implode(', ', $amazonOrderIds) . ')' : ''),
            'Amazon  inventory items: ' . count($amazonInventory),
            'Product catalog lines: ' . ($productLines ? implode(' ; ', $productLines) : 'No products available'),
            'Note: Amazon product pricing is not stored explicitly in this system unless the Shopify product record includes that detail.',
        ]));
    }

    private function getAmazonOrdersCache(?Shop $shop): array
    {
        if (!$shop) {
            return [];
        }

        return Cache::get('amazon_orders_' . $shop->shop, []);
    }

    private function getAmazonInventoryCache(?Shop $shop): array
    {
        if (!$shop || empty($shop->amazon_marketplace_id)) {
            return [];
        }

        return Cache::get("amazon_inventory_{$shop->id}_{$shop->amazon_marketplace_id}", []);
    }

    private function sendAiRequest(string $systemPrompt, string $userPrompt): array
    {
        $config = $this->configService->get();
        $payload = $this->buildPayload($systemPrompt, $userPrompt, $config);
        $endpoint = str_replace('{model}', $config['model'], $config['endpoint']);

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->timeout(60)
                ->retry(2, 500);

            if (($config['provider'] ?? 'openai') === 'gemini') {
                $request = $request->withHeaders(['x-goog-api-key' => $config['api_key']]);
            } else {
                $request = $request->withToken($config['api_key']);
            }

            $response = $request->post($endpoint, $payload);
            $response->throw();
            $data = $response->json();

            $message = $this->extractResponseText($data, $config['provider'] ?? 'openai');
            if ($message === null) {
                return [
                    'success' => false,
                    'message' => '',
                    'error' => 'AI returned an unexpected response format.',
                ];
            }

            return [
                'success' => true,
                'message' => trim($message),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('AIController sendAiRequest failure', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'message' => '',
                'error' => 'Unable to reach AI service: ' . $e->getMessage(),
            ];
        }
    }

    private function buildPayload(string $systemPrompt, string $userPrompt, array $config): array
    {
        $maxTokens = max(1, (int) ($config['max_tokens'] ?? 1024));

        if (($config['provider'] ?? 'openai') === 'gemini') {
            return [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userPrompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => (float) $config['temperature'],
                    'maxOutputTokens' => $maxTokens,
                ],
            ];
        }

        return [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => (float) $config['temperature'],
            'max_tokens' => $maxTokens,
        ];
    }

    private function extractResponseText(array $data, string $provider): ?string
    {
        if ($provider === 'gemini') {
            return data_get($data, 'candidates.0.content.parts.0.text');
        }

        return data_get($data, 'choices.0.message.content');
    }
}
