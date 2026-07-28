<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\ReturnItem;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Cache;
use App\Services\ShopifyService;
use App\Services\AmazonService;

class ReturnController extends ShopifyController
{
    public function index(Request $req){

        $shopModel = $this->getActiveShop($req);
        $activeShop = $shopModel?->shop;
        $shop = Shop::where('shop', $activeShop)->first();   
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }
        session(['shop' => $activeShop,'access_token'=>$shop->access_token,'region'=>$shop->amazon_mws_region]);

        $returns = ReturnItem::where('shop_id',$shop->id)->paginate();
        return view('Return.index', compact('activeShop','shop','returns'));

    }
    
    public function amazon(Request $req)
    {
        $shopModel = $this->getActiveShop($req);
    
        $shop = Shop::where('shop', $shopModel->shop)->first();
    
        if (!$shop || !$shop->amazon_access_token) {
            return response()->json([]);
        }
        $accessToken = $this->getAccessToken($shop);
        
        $amazon = new AmazonService($shop->amazon_mws_region);
    
        return response()->json(
            Cache::remember("amazon_returns_{$shop->merchant_id}", 600, function () use ($amazon) {
                $reportId = $amazon->createReturnsReport($shop->amazon_marketplace_id);
                $content = $amazon->getReport($reportId);
                $rows = $amazon->parseReport($content);
    
                $result = [];
    
                foreach ($rows as $row) {
    
                    $result[] = [
                        'order_id' => $row['order-id'] ?? '',
                        'product_name' => $row['product-name'] ?? '',
                        'sku' => $row['sku'] ?? '',
                        'quantity' => $row['quantity'] ?? 1,
                        'status' => 'returned',
                        'refund_amount' => $row['refund-amount'] ?? 0,
                        'reason' => $row['return-reason-code'] ?? '',
                        'created_at' => $row['return-date'] ?? now(),
                    ];
                }
    
                return $result;
            })
        );
    }
    
    public function shopify()
    {
        $shop = session('shop');
        $token = session('access_token');
    
        $shopify = new ShopifyService($shop, $token);
    
        $structure = [
            'id',
            'name',
            'createdAt',
    
            'customer' => [
                'firstName',
                'lastName',
                'email'
            ],
    
            'refunds' => [
                'id',
                'createdAt',
    
                'totalRefundedSet' => [
                    'shopMoney' => [
                        'amount',
                        'currencyCode'
                    ]
                ],
    
                'refundLineItems(first: 10)' => [
                    'nodes' => [
                        'quantity',
                        'lineItem' => [
                            'title',
                            'sku'
                        ]
                    ]
                ]
            ]
        ];
    
        $allData = [];
        $cursor = null;
    
        do {
            $res = $shopify->paginateRefunds($structure, 50, $cursor, "financial_status:refunded");
    
            $allData = array_merge($allData, $res['data']);
            $cursor = $res['next_cursor'];
    
        } while ($res['has_next']);
    
        $result = [];
        foreach ($allData as $order) {

            foreach ($order['refunds'] ?? [] as $refund) {
    
                $amount = $refund['totalRefundedSet']['shopMoney']['amount'] ?? 0;
                $currency = $refund['totalRefundedSet']['shopMoney']['currencyCode'] ?? '';
                $items = $refund['refundLineItems']['nodes'] ?? [];
                if (!empty($items)) {
    
                    foreach ($items as $item) {
    
                        $line = $item['lineItem'] ?? [];
                        $oid = str_replace('gid://shopify/Order/', '', $order['id']);
                        $result[] = [
                            'oid' => $oid,
                            'order_id' => $order['name'],
                            'product_name' => $line['title'] ?? 'N/A',
                            'sku' => $line['sku'] ?? 'N/A',
                            'quantity' => $item['quantity'] ?? 1,
                            'status' => 'refunded',
                            'refund_amount' => $amount,
                            'currency' => $currency,
                            'type' => 'product', 
                            'created_at' => $refund['createdAt'] ?? $order['createdAt'],
                        ];
                    }
    
                } else {
                 
                    $oid = str_replace('gid://shopify/Order/', '', $order['id']);
                    $result[] = [
                        'oid' => $oid,
                        'order_id' => $order['name'],
                        'product_name' => 'Manual Refund',
                        'sku' => '-',
                        'quantity' => 1,
                        'status' => 'refunded',
                        'refund_amount' => $amount,
                        'currency' => $currency,
                        'type' => 'manual', 
                        'created_at' => $refund['createdAt'] ?? $order['createdAt'],
                    ];
                }
            }
        }
    
        return response()->json($result);
    }

    public function viewAmazon(Request $request, $orderId)
    {
        $shop = session('shop');
        $token = session('access_token');
        $shopify = new ShopifyService($shop, $token);

        $data = $shopify->getRefundDetails($orderId);

        $order = $data['data']['order'] ?? null;

        if (!$order) {
            return back()->with('error', 'Order not found');
        }

        $result = [];

        foreach ($order['refunds'] ?? [] as $refund) {

            $amount = $refund['totalRefundedSet']['shopMoney']['amount'] ?? 0;
            $currency = $refund['totalRefundedSet']['shopMoney']['currencyCode'] ?? '';
            $items = $refund['refundLineItems']['nodes'] ?? [];

            if (!empty($items)) {
                foreach ($items as $item) {
                    $line = $item['lineItem'] ?? [];

                    $result[] = [
                        'order_id' => $order['name'],
                        'product_name' => $line['title'] ?? 'N/A',
                        'sku' => $line['sku'] ?? 'N/A',
                        'quantity' => $item['quantity'] ?? 1,
                        'refund_amount' => $amount,
                        'currency' => $currency,
                        'created_at' => $refund['createdAt'],
                        'type' => 'product'
                    ];
                }
            } else {
                $result[] = [
                    'order_id' => $order['name'],
                    'product_name' => 'Manual Refund',
                    'sku' => '-',
                    'quantity' => 1,
                    'refund_amount' => $amount,
                    'currency' => $currency,
                    'created_at' => $refund['createdAt'],
                    'type' => 'manual'
                ];
            }
        }

        return view('returns.view', [
            'order' => $order,
            'refunds' => $result
        ]);


    }

    public function viewShopify(Request $request, $orderId)
    {
        $shop = session('shop');
        $token = session('access_token');
     
        $shopify = new ShopifyService($shop, $token);

        $data = $shopify->getRefundDetails($orderId);

        $order = $data['data']['order'] ?? null;

        if (!$order) {
            return back()->with('error', 'Order not found');
        }

        $result = [];

        foreach ($order['refunds'] ?? [] as $refund) {

            $amount = $refund['totalRefundedSet']['shopMoney']['amount'] ?? 0;
            $currency = $refund['totalRefundedSet']['shopMoney']['currencyCode'] ?? '';
            $items = $refund['refundLineItems']['nodes'] ?? [];

            if (!empty($items)) {
                foreach ($items as $item) {
                    $line = $item['lineItem'] ?? [];

                    $result[] = [
                        'order_id' => $order['name'],
                        'product_name' => $line['title'] ?? 'N/A',
                        'sku' => $line['sku'] ?? 'N/A',
                        'quantity' => $item['quantity'] ?? 1,
                        'refund_amount' => $amount,
                        'currency' => $currency,
                        'created_at' => $refund['createdAt'],
                        'type' => 'product'
                    ];
                }
            } else {
                $result[] = [
                    'order_id' => $order['name'],
                    'product_name' => 'Manual Refund',
                    'sku' => '-',
                    'quantity' => 1,
                    'refund_amount' => $amount,
                    'currency' => $currency,
                    'created_at' => $refund['createdAt'],
                    'type' => 'manual'
                ];
            }
        }

        return view('Return.view', [
            'order' => $order,
            'refunds' => $result
        ]);
    }

}
