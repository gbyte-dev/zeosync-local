<?php

namespace App\Console\Commands;

use App\Jobs\CheckStoreStatusJob;
use App\Models\Shop;
use Illuminate\Console\Command;

class CheckStoreStatusCommand extends Command
{
    protected $signature = 'stores:check-status';

    protected $description = 'Check Shopify store status for all active shops';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Store status check started...');

        $count = 0;

        Shop::query()
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereNull('last_status_check_at')
                    ->orWhere(
                        'last_status_check_at',
                        '<=',
                        now()->subDay()
                    );
            })
            ->chunkById(100, function ($shops) use (&$count) {

                foreach ($shops as $shop) {

                    CheckStoreStatusJob::dispatch($shop);

                    $count++;

                    $this->line("Queued : {$shop->shop}");
                }
            });

        if ($count === 0) {

            $this->info('No shops require status check.');

            return self::SUCCESS;
        }

        $this->info("Total Jobs Dispatched : {$count}");

        return self::SUCCESS;
    }
}
