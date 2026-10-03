<?php

namespace App\Console\Commands;

use App\Models\AiChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeOldAiChatMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-chat:purge-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge AI chat messages older than 5 days based on individual message creation timestamp';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(5);

        $deletedCount = AiChatMessage::where('created_at', '<', $cutoff)->delete();

        $message = "Purged {$deletedCount} AI chat messages older than 5 days (cutoff: {$cutoff->toIso8601String()}).";
        $this->info($message);

        if ($deletedCount > 0) {
            Log::info('AI_CHAT_MESSAGES_PURGED', [
                'deleted_count' => $deletedCount,
                'cutoff' => $cutoff->toIso8601String(),
            ]);
        }

        return self::SUCCESS;
    }
}
