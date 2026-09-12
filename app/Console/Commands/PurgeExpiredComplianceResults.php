<?php
namespace App\Console\Commands;
use App\Models\ComplianceRequest;
use Illuminate\Console\Command;
class PurgeExpiredComplianceResults extends Command
{
    protected $signature = 'compliance:purge-expired-results';
    protected $description = 'Purge encrypted compliance request/result payloads after their retention deadline';
    public function handle(): int
    {
        ComplianceRequest::whereNotNull('purge_after')->where('purge_after','<=',now())->eachById(function ($r) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete('compliance/'.$r->shop_id.'/'.$r->id.'.json');
        });
        $count = ComplianceRequest::whereNotNull('purge_after')->where('purge_after','<=',now())->update(['request_payload'=>null,'result_payload'=>null,'error'=>null]);
        $this->info("Purged {$count} expired compliance payload(s).");
        return self::SUCCESS;
    }
}
