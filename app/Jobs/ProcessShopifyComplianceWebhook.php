<?php
namespace App\Jobs;
use App\Models\ComplianceRequest;
use App\Services\Webhooks\ShopifyComplianceProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
class ProcessShopifyComplianceWebhook implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public int $timeout = 120;
    public bool $failOnTimeout = true;
    public function __construct(private readonly int $requestId) { $this->onQueue('compliance'); }
    public function backoff(): array { return [10,30,120,300]; }
    public function handle(ShopifyComplianceProcessor $processor): void
    {
        $request = DB::transaction(function () {
            $request = ComplianceRequest::whereKey($this->requestId)->lockForUpdate()->firstOrFail();
            if (in_array($request->status,['completed','awaiting_delivery','review_required','revoked'],true)) { return null; }
            if ($request->status === 'processing' && $request->updated_at?->gt(now()->subMinutes(5))) {
                throw new \RuntimeException('Processing lease is still held; retry this delivery.');
            }
            $request->update(['status'=>'processing','error'=>null]);
            return $request;
        }, 3);
        if (!$request) { return; }
        try { $processor->process($request); }
        catch (\Throwable $e) {
            ComplianceRequest::whereKey($this->requestId)->update(['status'=>'failed','error'=>substr($e->getMessage(),0,2000)]);
            throw $e;
        }
    }
}
