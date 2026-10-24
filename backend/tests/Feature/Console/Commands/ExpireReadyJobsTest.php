<?php

use App\Enums\PrintJobStatus;
use App\Models\Location;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['printing.ready_expiration_days' => 7]);
});

it('expires ready jobs older than the configured days', function () {
    $old = PrintJob::factory()->status(PrintJobStatus::Ready)->create(['ready_at' => now()->subDays(8)]);
    $recent = PrintJob::factory()->status(PrintJobStatus::Ready)->create(['ready_at' => now()->subDays(6)]);

    $this->artisan('jobs:expire')->expectsOutput('Expired 1 job(s).')->assertSuccessful();

    expect($old->fresh()->status)->toBe(PrintJobStatus::Expired)
        ->and($recent->fresh()->status)->toBe(PrintJobStatus::Ready);
});

it('uses the location override instead of the default days', function () {
    $location = Location::factory()->create(['ready_expiration_days' => 2]);
    $job = PrintJob::factory()->status(PrintJobStatus::Ready)
        ->create(['location_id' => $location->id, 'ready_at' => now()->subDays(3)]);

    $this->artisan('jobs:expire')->assertSuccessful();

    expect($job->fresh()->status)->toBe(PrintJobStatus::Expired);
});

it('does not expire jobs that are not ready', function () {
    $job = PrintJob::factory()->status(PrintJobStatus::Printed)->create(['ready_at' => now()->subDays(30)]);

    $this->artisan('jobs:expire')->assertSuccessful();

    expect($job->fresh()->status)->toBe(PrintJobStatus::Printed);
});
