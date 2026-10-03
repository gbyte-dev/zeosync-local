<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAiChatController extends Controller
{
    /**
     * Display the Admin AI & Support Chat conversations dashboard.
     */
    public function index(Request $request)
    {
        // 1. Fetch shops that have messages, sorted by latest message descending
        $shopsWithMessages = Shop::query()
            ->whereHas('aiChatMessages')
            ->withCount('aiChatMessages')
            ->with(['aiChatMessages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->get()
            ->sortByDesc(function ($shop) {
                return $shop->aiChatMessages->first()?->created_at?->timestamp ?? 0;
            })
            ->values();

        // 2. Fetch all shops for quick switcher / search
        $allShops = Shop::query()
            ->orderBy('shop', 'asc')
            ->get(['id', 'shop', 'shop_name', 'email', 'is_active']);

        // 3. Resolve selected shop
        $selectedShopId = $request->query('shop_id');
        $selectedShop = null;

        if ($selectedShopId) {
            $selectedShop = Shop::find($selectedShopId);
        }

        if (!$selectedShop && $shopsWithMessages->isNotEmpty()) {
            $selectedShop = $shopsWithMessages->first();
        } elseif (!$selectedShop && $allShops->isNotEmpty()) {
            $selectedShop = $allShops->first();
        }

        // 4. Fetch messages for the active shop
        $messages = collect();
        if ($selectedShop) {
            $messages = AiChatMessage::where('shop_id', $selectedShop->id)
                ->orderBy('id', 'asc')
                ->get();
        }

        return view('admin.aichat.index', [
            'shopsWithMessages' => $shopsWithMessages,
            'allShops' => $allShops,
            'selectedShop' => $selectedShop,
            'messages' => $messages,
        ]);
    }

    /**
     * Fetch lightweight conversation summary list for admin polling and live sorting.
     */
    public function conversations(Request $request)
    {
        $shopsWithMessages = Shop::query()
            ->whereHas('aiChatMessages')
            ->withCount('aiChatMessages')
            ->with(['aiChatMessages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->get()
            ->sortByDesc(function ($shop) {
                return $shop->aiChatMessages->first()?->created_at?->timestamp ?? 0;
            })
            ->values();

        $data = $shopsWithMessages->map(function ($shop) {
            $latest = $shop->aiChatMessages->first();
            return [
                'id' => $shop->id,
                'shop' => $shop->shop,
                'shop_name' => $shop->shop_name ?: $shop->shop,
                'email' => $shop->email,
                'is_active' => (int) $shop->is_active,
                'messages_count' => (int) $shop->ai_chat_messages_count,
                'latest_message' => $latest ? [
                    'id' => $latest->id,
                    'role' => $latest->role,
                    'message' => $latest->message,
                    'created_at' => $latest->created_at?->toIso8601String(),
                    'formatted_time' => $latest->created_at ? $latest->created_at->diffForHumans(null, true, true) : '',
                    'timestamp' => $latest->created_at?->timestamp ?? 0,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'shops' => $data,
        ]);
    }

    /**
     * Fetch messages for a specific shop (supporting polling with after_id).
     */
    public function messages(Request $request, Shop $shop)
    {
        $afterId = $request->query('after_id');
        $query = AiChatMessage::where('shop_id', $shop->id);

        if ($afterId && is_numeric($afterId)) {
            $query->where('id', '>', (int) $afterId);
        }

        $messages = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'shop' => [
                'id' => $shop->id,
                'shop' => $shop->shop,
                'shop_name' => $shop->shop_name ?: $shop->shop,
                'email' => $shop->email,
            ],
            'messages' => $messages->map(fn($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'message' => $m->message,
                'created_at' => $m->created_at?->toIso8601String(),
                'formatted_time' => $m->created_at?->format('M d, h:i A'),
            ]),
        ]);
    }

    /**
     * Send an admin message to the specified shop's conversation.
     */
    public function sendMessage(Request $request, Shop $shop)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = trim($request->input('message'));

        $chatMessage = AiChatMessage::create([
            'shop_id' => $shop->id,
            'role' => 'admin',
            'message' => $message,
        ]);

        Log::info('ADMIN_CHAT_MESSAGE_SENT', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
            'message_id' => $chatMessage->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $chatMessage->id,
                    'role' => $chatMessage->role,
                    'message' => $chatMessage->message,
                    'created_at' => $chatMessage->created_at?->toIso8601String(),
                    'formatted_time' => $chatMessage->created_at?->format('M d, h:i A'),
                ],
            ]);
        }

        return redirect()->route('admin.aichats.index', ['shop_id' => $shop->id])
            ->with('success', 'Message sent successfully.');
    }

    /**
     * Clear all conversation messages for a shop.
     */
    public function clearChat(Request $request, Shop $shop)
    {
        $count = AiChatMessage::where('shop_id', $shop->id)->delete();

        Log::info('ADMIN_CHAT_CLEARED', [
            'shop_id' => $shop->id,
            'deleted_count' => $count,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'deleted_count' => $count,
                'message' => 'Chat history cleared successfully.',
            ]);
        }

        return redirect()->route('admin.aichats.index', ['shop_id' => $shop->id])
            ->with('success', 'Chat history cleared successfully.');
    }
}
