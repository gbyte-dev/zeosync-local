<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use Illuminate\Support\Facades\Cache;
use SellingPartnerApi\Seller\ReportsV20210630\Dto\CreateReportSpecification;

class AmazonSandboxController extends Controller
{
    private function getConnector()
    {
        return SellingPartnerApi::seller(
            clientId: config('amazon.client_id'),
            clientSecret: config('amazon.client_secret'),
            refreshToken: config('amazon.refresh_token'),
            endpoint: Endpoint::NA_SANDBOX
        );
    }

    // 🔥 Orders Debug
    public function debugOrders()
    {
        try {
            $connector = $this->getConnector();

            $response = $connector->ordersV0()->getOrders(
                marketplaceIds: ['ATVPDKIKX0DER'],
                createdAfter: 'TEST_CASE_200'
            );

            return response()->json(json_decode($response->body(), true));
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function debugProduct(Request $request)
    {
        try {
            $asin = $request->get('asin', 'B07N4M94X4');

            $connector = $this->getConnector();

            $response = $connector->catalogItems20220401()->getCatalogItem(
                asin: $asin,
                marketplaceIds: ['ATVPDKIKX0DER']
            );

            return response()->json(json_decode($response->body(), true));
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function orderDetail($id)
    {
        $order = Cache::get('amazon_order_' . $id);

        if (!$order) {
            $this->fetchAmazonOrders(true);
            $order = Cache::get('amazon_order_' . $id);
        }

        if (!$order) {
            abort(404, 'Order not found');
        }

        return view('amazon-order-detail', compact('order'));
    }


    public function testOrderFilters()
    {
        try {

            $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                clientId: config('amazon.client_id'),
                clientSecret: config('amazon.client_secret'),
                refreshToken: config('amazon.refresh_token'),
                endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
            );

            $response = $connector->ordersV0()->getOrders(
                ['ATVPDKIKX0DER'],
                'TEST_CASE_200',
                null,
                null,
                null,
                ['Pending'] // 🔥 change this
            );

            $data = json_decode($response->body(), true);

            return response()->json([
                'orders' => $data['payload']['Orders'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function listReports()
    {
        try {

            $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                clientId: config('amazon.client_id'),
                clientSecret: config('amazon.client_secret'),
                refreshToken: config('amazon.refresh_token'),
                endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
            );

            $response = $connector->reportsV20210630()->getReports(
                reportTypes: ['GET_FLAT_FILE_OPEN_LISTINGS_DATA'],
                processingStatuses: ['IN_QUEUE', 'IN_PROGRESS', 'DONE']
            );

            $data = json_decode($response->body(), true);

            $reports = $data['reports'] ?? [];

            return view('amazon-reports', compact('reports'));
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createReturnReport()
    {
        try {

            $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                clientId: config('amazon.client_id'),
                clientSecret: config('amazon.client_secret'),
                refreshToken: config('amazon.refresh_token'),
                endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
            );

            // 🔥 DTO OBJECT (IMPORTANT)
            $reportRequest = new CreateReportSpecification(
                reportType: 'GET_MERCHANT_LISTINGS_ALL_DATA',
                marketplaceIds: ['ATVPDKIKX0DER', 'A1PA6795UKMFR9'],
                dataStartTime: new \DateTime('2024-03-10T20:11:24.000Z')
            );

            $response = $connector->reportsV20210630()->createReport($reportRequest);

            $data = json_decode($response->body(), true);

            return response()->json([
                'success' => true,
                'reportId' => $data['reportId'] ?? null,
                'full_response' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
