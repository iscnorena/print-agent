<?php

namespace App\Console\Commands;

use App\Enums\PrintJobStatus;
use App\Models\Location;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:expire')]
#[Description('Expire jobs left in "ready" longer than their location allows and delete their files')]
class ExpireReadyJobs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrintJobService $service): int
    {
        $expired = 0;

        Location::query()->each(function (Location $location) use ($service, &$expired): void {
            PrintJob::query()
                ->whereBelongsTo($location)
                ->where('status', PrintJobStatus::Ready)
                ->where('ready_at', '<=', now()->subDays($location->readyExpirationDays()))
                ->lazyById()
                ->each(function (PrintJob $job) use ($service, &$expired): void {
                    $service->expire($job);
                    $expired++;
                });
        });

        $this->info("Expired {$expired} job(s).");

        return self::SUCCESS;
    }
}
