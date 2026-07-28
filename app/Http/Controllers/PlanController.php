<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Mail\SubscriptionCancelledMail;
use Illuminate\Support\Facades\Mail;
use App\Models\ShopSubscription;
use App\Models\ShopifySubscription;
use Illuminate\Support\Facades\Log;
use App\Services\StripeService;
use App\Models\Shop;

class PlanController extends Controller
{


    protected StripeService $stripeService;
    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    public function index()
    {
        $plans = Plan::orderBy('sort_order')->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'badge' => 'nullable',
            'description' => 'nullable',
            'features' => 'nullable',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'trial_days' => 'nullable|integer',
            'sync_limit' => 'required|integer|min:0',
            'product_limit' => 'required|integer|min:0',

            //  IMPORTANT FIX
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric',

            'stripe_price_ids' => 'nullable|array',
            'is_trial' => 'nullable|boolean',
            'ai_autofill' => 'nullable|boolean',
            'ai_single_field' => 'nullable|boolean',
        ]);

        $data['ai_autofill'] = $request->boolean('ai_autofill');
        $data['ai_single_field'] = $request->boolean('ai_single_field');

        //  HANDLE TRIAL PLAN
        if ($request->has('is_trial')) {
            $data['prices'] = [
                'EVERY_30_DAYS' => 0
            ];
            $data['is_trial'] = 1;
        } else {
            $data['prices'] = array_filter($request->prices ?? [], function ($value) {
                return $value !== null && $value !== '';
            });

            //  ensure at least one price exists
            if (empty($data['prices'])) {
                return back()->withErrors([
                    'prices' => 'At least one pricing option is required'
                ])->withInput();
            }

            $data['is_trial'] = 0;
        }

        //  slug
        $data['slug'] = Str::slug($request->name);

        Plan::create($data);

        return redirect()->route('admin.plans')->with('success', 'Plan created');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => 'required',
            'badge' => 'nullable',
            'description' => 'nullable',
            'features' => 'nullable',
            'is_highlighted' => 'boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'trial_days' => 'nullable|integer',
            'sync_limit' => 'required|integer|min:0',
            'product_limit' => 'required|integer|min:0',

            //  FIX
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric',

            'stripe_price_ids' => 'nullable|array',
            'is_trial' => 'nullable|boolean',
            'ai_autofill' => 'nullable|boolean',
            'ai_single_field' => 'nullable|boolean',
        ]);

        $data['ai_autofill'] = $request->boolean('ai_autofill');
        $data['ai_single_field'] = $request->boolean('ai_single_field');

        //  HANDLE TRIAL PLAN
        if ($request->has('is_trial')) {
            $data['prices'] = [
                'EVERY_30_DAYS' => 0
            ];
            $data['is_trial'] = 1;

            // optional clean
            $data['stripe_price_ids'] = [];
        } else {
            $data['prices'] = array_filter($request->prices ?? [], function ($value) {
                return $value !== null && $value !== '';
            });

            //  at least one price required
            if (empty($data['prices'])) {
                return back()->withErrors([
                    'prices' => 'At least one pricing option is required'
                ])->withInput();
            }

            $data['is_trial'] = 0;
        }

        //  slug update
        $data['slug'] = Str::slug($request->name);

        $plan->update($data);

        return redirect()->route('admin.plans')->with('success', 'Plan updated');
    }

    public function cancel(Request $request)
    {
        Log::info('PLAN CANCEL REQUEST', [
            'shop' => $request->input('shop'),
        ]);

        $shop = Shop::where('shop', $request->input('shop'))->first();

        if (!$shop) {
            Log::error('Shop not found', [
                'shop' => $request->input('shop'),
            ]);

            return back()->with('error', 'Shop not found.');
        }

        Log::info('SHOP FOUND', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
        ]);

        $shopifySubscription = ShopifySubscription::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        if (!$shopifySubscription) {

            Log::warning('No active Stripe subscription found', [
                'shop_id' => $shop->id,
            ]);

            return back()->with('error', 'No active Stripe subscription found.');
        }

        Log::info('ACTIVE SUB FROM DB', [
            'db_row_id' => $shopifySubscription->id,
            'stripe_subscription_id' => $shopifySubscription->stripe_subscription_id,
            'status' => $shopifySubscription->status,
            'payment_status' => $shopifySubscription->payment_status,
            'created_at' => $shopifySubscription->created_at,
        ]);

        Log::info('SENDING CANCEL REQUEST TO STRIPE', [
            'subscription_id' => $shopifySubscription->stripe_subscription_id,
        ]);

        $result = $this->stripeService->cancelSubscription(
            $shopifySubscription->stripe_subscription_id
        );

        Log::info('STRIPE CANCEL RESULT', [
            'result' => $result,
        ]);

        if (!$result) {

            Log::error('Stripe cancellation failed', [
                'subscription_id' => $shopifySubscription->stripe_subscription_id,
            ]);

            return back()->with(
                'error',
                'Unable to cancel Stripe subscription.'
            );
        }

        Log::info('Stripe subscription cancellation initiated', [
            'shop_id' => $shop->id,
            'db_row_id' => $shopifySubscription->id,
            'subscription_id' => $shopifySubscription->stripe_subscription_id,
        ]);

        // Database update webhook karega

        return back()->with(
            'success',
            'Subscription cancellation initiated successfully.'
        );
    }
}
