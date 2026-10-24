<?php

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->printer = Printer::factory()->withToken('pa_secret')->create();
    $this->withToken('pa_secret');
});

describe('next', function () {
    it('returns 204 when the printer has no queued jobs', function () {
        PrintJob::factory()->status(PrintJobStatus::Ready)->create(['printer_id' => $this->printer->id]);

        $this->getJson(route('agent.jobs.next'))->assertNoContent();
    });

    it('returns the oldest queued job with its print options', function () {
        $oldest = PrintJob::factory()->queuedOn($this->printer)->create([
            'queued_at' => now()->subMinutes(5),
            'copies' => 2,
            'color' => true,
            'page_range' => '1-3',
        ]);
        PrintJob::factory()->queuedOn($this->printer)->create(['queued_at' => now()]);

        $this->getJson(route('agent.jobs.next'))
            ->assertOk()
            ->assertJsonPath('data.id', $oldest->id)
            ->assertJsonPath('data.copies', 2)
            ->assertJsonPath('data.color', true)
            ->assertJsonPath('data.duplex', 'simplex')
            ->assertJsonPath('data.page_range', '1-3')
            ->assertJsonPath('data.file_url', route('agent.jobs.file', $oldest));
    });

    it('does not return jobs queued on another printer', function () {
        PrintJob::factory()->queuedOn(Printer::factory()->create())->create();

        $this->getJson(route('agent.jobs.next'))->assertNoContent();
    });
});

describe('file', function () {
    it('streams the PDF of a queued job', function () {
        Storage::fake('local');
        $job = PrintJob::factory()->queuedOn($this->printer)->create(['pdf_path' => 'print-jobs/a.pdf']);
        Storage::disk('local')->put('print-jobs/a.pdf', '%PDF-test');

        $response = $this->get(route('agent.jobs.file', $job))->assertOk();

        expect($response->streamedContent())->toBe('%PDF-test');
    });

    it('returns 404 for a job of another printer', function () {
        Storage::fake('local');
        $job = PrintJob::factory()->queuedOn(Printer::factory()->create())->create(['pdf_path' => 'print-jobs/a.pdf']);
        Storage::disk('local')->put('print-jobs/a.pdf', '%PDF-test');

        $this->getJson(route('agent.jobs.file', $job))->assertNotFound();
    });

    it('returns 404 for a job that is not queued or printing', function () {
        Storage::fake('local');
        $job = PrintJob::factory()->status(PrintJobStatus::Printed)
            ->create(['printer_id' => $this->printer->id, 'pdf_path' => 'print-jobs/a.pdf']);
        Storage::disk('local')->put('print-jobs/a.pdf', '%PDF-test');

        $this->getJson(route('agent.jobs.file', $job))->assertNotFound();
    });

    it('returns 404 when the PDF is missing from storage', function () {
        Storage::fake('local');
        $job = PrintJob::factory()->queuedOn($this->printer)->create();

        $this->getJson(route('agent.jobs.file', $job))->assertNotFound();
    });
});

describe('status', function () {
    it('moves a queued job to printing and then printed', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create();

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'printing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'printing');
        $this->postJson(route('agent.jobs.status', $job), ['status' => 'printed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'printed');

        $job->refresh();
        expect($job->status)->toBe(PrintJobStatus::Printed)
            ->and($job->printed_at)->not->toBeNull();
    });

    it('stores the error message when the job fails', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create(['status' => PrintJobStatus::Printing]);

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'failed', 'error' => 'Paper jam'])
            ->assertOk();

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Failed)
            ->error_message->toBe('Paper jam');
    });

    it('accepts a repeated report of the current status', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create(['status' => PrintJobStatus::Printed, 'printed_at' => now()]);

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'printed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'printed');
    });

    it('returns 409 for a transition that is not allowed', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create();

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'printed'])
            ->assertConflict()
            ->assertJsonPath('current_status', 'queued');

        expect($job->fresh()->status)->toBe(PrintJobStatus::Queued);
    });

    it('returns 422 for a status the agent may not set', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create();

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('returns 422 when a failure has no error message', function () {
        $job = PrintJob::factory()->queuedOn($this->printer)->create(['status' => PrintJobStatus::Printing]);

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'failed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('error');
    });

    it('returns 404 for a job of another printer', function () {
        $job = PrintJob::factory()->queuedOn(Printer::factory()->create())->create();

        $this->postJson(route('agent.jobs.status', $job), ['status' => 'printing'])->assertNotFound();

        expect($job->fresh()->status)->toBe(PrintJobStatus::Queued);
    });
});
