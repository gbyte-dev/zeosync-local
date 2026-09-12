<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\UpgradePreflight;
class ReleasePreflight extends Command
{
    protected $signature = 'release:preflight';
    protected $description = 'Read-only report of records that block integrity constraints';
    public function handle(UpgradePreflight $preflight): int
    {
        $conflicts = $preflight->conflicts();
        $this->line(json_encode(['safe' => !$conflicts,'conflicts' => $conflicts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $conflicts ? self::FAILURE : self::SUCCESS;
    }
}
