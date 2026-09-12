<?php
namespace App\Services\Webhooks;

use App\Models\{Plan, Shop, ShopSubscription, ShopifySubscription, WebhookEvent};
use App\Services\StripeService;
use Illuminate\Support\Facades\{Cache, DB};
use RuntimeException;

class StripeWebhookProcessor
{
    public function process(WebhookEvent $event): void
    {
        Cache::lock('stripe:event:'.$event->id, 180)->block(5, function () use ($event) {
            $event->refresh();
            if (in_array($event->status,['processed','ignored','review_required'],true)) { return; }
            if ($event->attempts >= 20) { $event->update(['status'=>'review_required']); return; }
            $event->update(['attempts'=>$event->attempts+1]);
            try {
                $supported = ['checkout.session.completed','customer.subscription.created','customer.subscription.updated','customer.subscription.deleted','invoice.payment_succeeded','invoice.payment_failed','invoice.paid'];
                if (!in_array($event->topic,$supported,true)) { $event->update(['status'=>'ignored','payload'=>null,'processed_at'=>now()]); return; }
                $this->reconcile($event);
            } catch (\Throwable $e) {
                $event->update(['status'=>$event->attempts >= 20 ? 'review_required' : 'failed','error'=>'Stripe reconciliation failed; inspect provider linkage and configuration.']);
                throw $e;
            }
        });
    }
    private function reconcile(WebhookEvent $event): void
    {
        if ($event->fresh()->status === 'processed') { return; }
        $object = data_get($event->payload, 'data.object');
        if (!is_array($object)) { throw new RuntimeException('Missing Stripe object.'); }
        $isCheckout = $event->topic === 'checkout.session.completed';
        $id = str_starts_with($event->topic ?? '', 'customer.subscription.') ? ($object['id'] ?? null)
            : ($object['subscription'] ?? data_get($object,'parent.subscription_details.subscription') ?? data_get($object,'lines.data.0.parent.subscription_item_details.subscription'));
        if (!$id) { throw new RuntimeException('Missing Stripe subscription identifier.'); }
        $local = ShopifySubscription::where('stripe_subscription_id',$id)->first();
        if (!$local && $isCheckout) {
            $local = ShopifySubscription::whereKey(data_get($object,'metadata.shopify_subscription_id'))->where('shop_id',data_get($object,'metadata.shop_id'))->first();
        }
        if (!$local) { throw new RuntimeException('Stripe event cannot be linked to a local subscription.'); }
        Cache::lock('privacy:shop:'.$local->shop_id, 300)->block(5, function () use ($event,$local,$id,$object,$isCheckout) {
        Cache::lock('billing:shop:'.$local->shop_id, 180)->block(5, function () use ($event,$local,$id,$object,$isCheckout) {
            $event->refresh();
            if ($event->status === 'processed') { return; }
            // This lock, not an independent timestamp claim, owns processing.
            $event->update(['status'=>'processing','error'=>null]);
            try {
                // Every distinct event reconciles current provider state. A delayed
                // invoice can never reactivate a canonically cancelled subscription.
                $remote = app(StripeService::class)->getSubscription($id);
                if (!$remote || data_get($remote,'id') !== $id) { throw new RuntimeException('Canonical Stripe state is unavailable.'); }
                $shop = Shop::findOrFail($local->shop_id);
                $customer = data_get($remote,'customer');
                if (is_object($customer)) { $customer = $customer->id; }
                $expectedCustomer = $local->stripe_customer_id ?: $shop->stripe_customer_id;
                if (!$expectedCustomer || $customer !== $expectedCustomer) { throw new RuntimeException('Stripe customer ownership mismatch.'); }
                $event->update(['shop_id'=>$shop->id]);
                $trialEnd = data_get($remote,'trial_end');
                $status = (string)data_get($remote,'status');
                $active = in_array($status,['active','trialing'],true);
                if ($status === 'trialing' && !$trialEnd) { throw new RuntimeException('Canonical trial end is missing.'); }
                $start = data_get($remote,'current_period_start') ?? data_get($remote,'items.data.0.current_period_start');
                $end = data_get($remote,'current_period_end') ?? data_get($remote,'items.data.0.current_period_end');
                if ($active && (!$start || !$end)) { throw new RuntimeException('Canonical Stripe billing period is missing.'); }
                $planId = data_get($remote,'metadata.plan_id') ?? ($isCheckout ? data_get($object,'metadata.plan_id') : null);
                $plan = $planId ? Plan::whereKey($planId)->where('is_active',true)->where(function($q) use ($shop) {
                    $q->where(fn($q)=>$q->where('is_custom',false)->whereNull('shop_id'))
                      ->orWhere(fn($q)=>$q->where('is_custom',true)->where('shop_id',$shop->id));
                })->first() : null;
                if ($planId && !$plan) { throw new RuntimeException('Provider plan is not available to this tenant.'); }
                // Superseded cancellation is idempotent and retried before local
                // activation commits. No time-relative dates are generated.
                if ($isCheckout && $active) {
                    foreach (ShopifySubscription::where('shop_id',$shop->id)->where('id','<',$local->id)->whereIn('status',['active','trialing'])->whereNotNull('stripe_subscription_id')->get() as $old) {
                        if (!app(StripeService::class)->cancelSubscription($old->stripe_subscription_id)) { throw new RuntimeException('Superseded subscription cancellation requires retry.'); }
                        $old->update(['status'=>'canceled','payment_status'=>'canceled']);
                    }
                }
                DB::transaction(function () use ($local,$id,$customer,$status,$start,$end,$active,$plan,$event,$shop,$trialEnd) {
                    $local->forceFill(['stripe_subscription_id'=>$id,'stripe_customer_id'=>$customer,'status'=>$status,
                        'payment_status'=>$active ? 'paid' : $status,
                        'current_period_start'=>$start ? date('Y-m-d H:i:s',(int)$start) : null,
                        'current_period_end'=>$end ? date('Y-m-d H:i:s',(int)$end) : null,
                        'last_stripe_event_at'=>(int)data_get($event->payload,'created',0),'last_stripe_event_id'=>$event->event_id])->save();
                    // An older subscription cannot revoke/replace a newer active one.
                    $newer = ShopifySubscription::where('shop_id',$shop->id)->where('id','>',$local->id)->whereIn('status',['active','trialing'])->exists();
                    if (!$newer) {
                        $entitlement = ShopSubscription::where('shop_id',$shop->id)->lockForUpdate()->first();
                        if (!$entitlement) { throw new RuntimeException('Local entitlement row is missing.'); }
                        $changes = ['trial_ends_at'=>$status === 'trialing' ? date('Y-m-d H:i:s',(int)$trialEnd) : null,'is_trial'=>$status === 'trialing','status'=>$active ? $status : 'canceled','current_period_end'=>$end ? date('Y-m-d H:i:s',(int)$end) : now()];
                        if ($active && $plan) { $changes += ['plan_id'=>$plan->id,'requested_plan_id'=>null,'is_trial'=>$status === 'trialing']; }
                        $entitlement->update($changes);
                    }
                    $event->update(['status'=>'processed','processed_at'=>now(),'error'=>null]);
                },3);
            } catch (\Throwable $e) {
                $event->update(['status'=>'failed','error'=>substr($e->getMessage(),0,1000)]);
                throw $e;
            }
        });
        });
    }
}
