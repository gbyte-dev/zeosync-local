<?php
namespace App\Jobs;
use App\Models\WebhookEvent;
use App\Services\Webhooks\ShopifyOrderWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class ProcessShopifyOrderWebhook implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public int $timeout = 150;
    public bool $failOnTimeout = true;
    public function __construct(private readonly int $eventId) { $this->onQueue('webhooks'); }
    public function backoff(): array { return [10,30,120,300]; }
    public function handle(ShopifyOrderWebhookProcessor $processor): void
    {
        $event = DB::transaction(function () {
            $event = WebhookEvent::whereKey($this->eventId)->lockForUpdate()->firstOrFail();
            if ($event->status === 'processed') { return null; }
            if ($event->status === 'processing' && $event->updated_at?->gt(now()->subMinutes(5))) {
                throw new \RuntimeException('Processing lease is still held; retry this delivery.');
            }
            $event->update(['status'=>'processing','attempts'=>$event->attempts+1,'error'=>null]);
            return $event;
        }, 3);
        if (!$event) { return; }
        try {
            $processor->process($event);
            $event->update(['status'=>'processed','processed_at'=>now(),'error'=>null]);
        } catch (\Throwable $e) {
            $event->update(['status'=>'failed','error'=>substr($e->getMessage(),0,2000)]);
            throw $e;
        }
    }
    public function failed(\Throwable $e): void
    {
        WebhookEvent::whereKey($this->eventId)->update(['status'=>'failed','error'=>substr($e->getMessage(),0,2000)]);
        Log::error('Shopify order webhook exhausted retries.', ['event_id'=>$this->eventId,'error'=>$e->getMessage()]);
    }
}
