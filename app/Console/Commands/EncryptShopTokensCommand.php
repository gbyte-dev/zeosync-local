<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncryptShopTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shops:encrypt-tokens {--dry-run : Simulate the migration without updating records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt plaintext access_token, refresh_token, and amazon_refresh_token credentials in the shops table';

    /**
     * The sensitive token fields to encrypt.
     *
     * @var array<int, string>
     */
    protected array $tokenFields = [
        'access_token',
        'refresh_token',
        'amazon_refresh_token',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('Running in DRY RUN mode. No database records will be modified.');
        }

        $totalShops = 0;
        $shopsToUpdate = 0;
        $fieldCounts = [
            'access_token' => 0,
            'refresh_token' => 0,
            'amazon_refresh_token' => 0,
        ];
        $alreadyEncryptedCount = 0;
        $skippedEmptyCount = 0;
        $errorsCount = 0;

        DB::table('shops')
            ->select('id', 'shop', 'access_token', 'refresh_token', 'amazon_refresh_token')
            ->orderBy('id')
            ->chunk(100, function ($shops) use (
                $isDryRun,
                &$totalShops,
                &$shopsToUpdate,
                &$fieldCounts,
                &$alreadyEncryptedCount,
                &$skippedEmptyCount,
                &$errorsCount
            ) {
                foreach ($shops as $shopRow) {
                    $totalShops++;
                    $updates = [];

                    foreach ($this->tokenFields as $field) {
                        $rawVal = $shopRow->{$field} ?? null;

                        if ($rawVal === null || $rawVal === '') {
                            $skippedEmptyCount++;
                            continue;
                        }

                        if ($this->isEncrypted($rawVal)) {
                            $alreadyEncryptedCount++;
                            continue;
                        }

                        try {
                            $encrypted = Crypt::encryptString($rawVal);
                            $updates[$field] = $encrypted;
                            $fieldCounts[$field]++;
                        } catch (Throwable $e) {
                            $errorsCount++;
                            $this->error("Failed to encrypt {$field} for shop ID {$shopRow->id} ({$shopRow->shop}): " . $e->getMessage());
                        }
                    }

                    if (!empty($updates)) {
                        $shopsToUpdate++;
                        if (!$isDryRun) {
                            DB::table('shops')
                                ->where('id', $shopRow->id)
                                ->update($updates);
                        }
                    }
                }
            });

        $this->newLine();
        $this->info('--- Encryption Summary ---');
        $this->line("Total shop records checked: {$totalShops}");
        $this->line(($isDryRun ? "Shops that would be updated: " : "Shops updated: ") . $shopsToUpdate);
        $this->line("Fields converted to ciphertext:");
        foreach ($fieldCounts as $field => $count) {
            $this->line("  - {$field}: {$count}");
        }
        $this->line("Already encrypted values skipped: {$alreadyEncryptedCount}");
        $this->line("Null or empty values skipped: {$skippedEmptyCount}");

        if ($errorsCount > 0) {
            $this->warn("Total encryption errors encountered: {$errorsCount}");
            return self::FAILURE;
        }

        $this->info($isDryRun ? 'Dry run completed successfully.' : 'Token encryption migration completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Determine if a value is already encrypted with Laravel application encryption.
     */
    protected function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
