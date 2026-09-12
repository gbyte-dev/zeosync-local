<?php
namespace App\Jobs;
use App\Models\WebhookEvent;
use App\Services\Webhooks\StripeWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessStripeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 5;
    public int $timeout = 90;
    public function __construct(public readonly int $webhookEventId) { $this->onQueue('webhooks'); }
    public function backoff(): array { return [10,30,90,300]; }
    public function handle(StripeWebhookProcessor $processor): void
    {
        $processor->process(WebhookEvent::findOrFail($this->webhookEventId));
    }
    public function failed(\Throwable $e): void
    {
        WebhookEvent::whereKey($this->webhookEventId)->whereNotIn('status',['processed','ignored','review_required'])->update(['status'=>'failed','error'=>'Stripe processing exhausted retries.']);
    }
}
