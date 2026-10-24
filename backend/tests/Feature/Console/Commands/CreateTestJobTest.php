<?php

use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->pdf = base_path('tests/Fixtures/one-page.pdf');
});

it('queues a job on the first printer with the given options', function () {
    $printer = Printer::factory()->create();

    $this->artisan('job:create-test', [
        'pdf' => $this->pdf, '--copies' => 2, '--color' => true, '--duplex' => 'long', '--pages' => '1',
    ])->assertSuccessful();

    $job = PrintJob::sole();
    expect($job)
        ->status->toBe(PrintJobStatus::Queued)
        ->printer_id->toBe($printer->id)
        ->folio->toBe((string) $job->id)
        ->copies->toBe(2)
        ->color->toBeTrue()
        ->duplex->toBe(Duplex::Long)
        ->page_range->toBe('1');
    Storage::disk('local')->assertExists($job->pdf_path);
});

it('rejects color on a printer without color support', function () {
    Printer::factory()->create(['supports_color' => false]);

    $this->artisan('job:create-test', ['pdf' => $this->pdf, '--color' => true])->assertFailed();

    expect(PrintJob::count())->toBe(0);
});

it('fails when no printer exists', function () {
    $this->artisan('job:create-test', ['pdf' => $this->pdf])->assertFailed();
});

it('rejects a file that is not a PDF', function () {
    Printer::factory()->create();

    $this->artisan('job:create-test', ['pdf' => base_path('composer.json')])->assertFailed();
});
