<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmazonWebhookController extends Controller
{
    /**
     * Receive Amazon order notifications.
     */
    public function handleOrderNotification(Request $request)
    {
        Log::info('========== AMAZON ORDER WEBHOOK START ==========');

        Log::info('Amazon Notification Headers', [
            'headers' => $request->headers->all(),
        ]);

        Log::info('Amazon Notification Raw Payload', [
            'payload' => $request->getContent(),
        ]);

        $payload = json_decode($request->getContent(), true);

        Log::info('Amazon Notification Parsed Payload', [
            'payload' => $payload,
        ]);

        $notificationType = $payload['NotificationType'] ?? null;
        $payloadData = $payload['Payload'] ?? [];

        $amazonOrderId = $payloadData['AmazonOrderId'] ?? null;

        Log::info('Amazon Notification Details', [
            'notification_type' => $notificationType,
            'amazon_order_id' => $amazonOrderId,
        ]);

        if (!is_array($payload)) {
            Log::warning('Invalid Amazon notification payload.');

            return response()->json([
                'success' => false,
                'message' => 'Invalid payload',
            ], 400);
        }

        Log::info('========== AMAZON ORDER WEBHOOK END ==========');

        return response()->json([
            'success' => true,
        ], 200);
    }
}
