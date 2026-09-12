<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\InventoryOperationService;
class ProcessInventoryOperation implements ShouldQueue
{
    use Queueable;
    public int $timeout = 150;
    public int $tries = 5;
    public function __construct(public readonly int $operationId) { $this->onQueue('inventory'); }
    public function backoff(): array { return [30,60,120,300]; }
    public function handle(InventoryOperationService $service): void { $service->process($this->operationId); }
}
