<?php

namespace App\Console\Commands;

use App\Models\DeviceLocation;
use Illuminate\Console\Command;

class PurgeExpiredLocations extends Command
{
    protected $signature = 'cdm:purge-locations';

    protected $description = 'Delete device location rows older than configured retention';

    public function handle(): int
    {
        $days = (int) config('cdm.location_retention_days', 90);
        $cutoff = now()->subDays($days);

        $deleted = DeviceLocation::query()
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        $this->info("Purged {$deleted} location rows older than {$days} days.");

        return self::SUCCESS;
    }
}
