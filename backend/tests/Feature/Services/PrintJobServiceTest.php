<?php

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Enums\PrintMode;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Support\Facades\Storage;

function quotedJobAt(PrintMode $printMode, array $attributes = []): PrintJob
{
    $location = Location::factory()->printMode($printMode)->create();
    Printer::factory()->create(['location_id' => $location->id]);

    return PrintJob::factory()->create(['location_id' => $location->id, ...$attributes]);
}

describe('confirm', function () {
    it('leaves the job ready in manual mode even when paid', function () {
        $job = quotedJobAt(PrintMode::Manual, ['paid_at' => now()]);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Ready)
            ->ready_at->not->toBeNull()
            ->printer_id->toBeNull();
    });

    it('assigns a folio with the last four digits of the customer phone', function () {
        $customer = Customer::factory()->create(['phone' => '7441234521']);
        $job = quotedJobAt(PrintMode::Manual, ['customer_id' => $customer->id]);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh()->folio)->toBe("{$job->id}-4521");
    });

    it('uses just the job number as folio when there is no customer phone', function () {
        $job = quotedJobAt(PrintMode::Manual, ['customer_id' => null]);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh()->folio)->toBe((string) $job->id);
    });

    it('queues the job on the location printer in auto mode', function () {
        $job = quotedJobAt(PrintMode::Auto);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Queued)
            ->queued_at->not->toBeNull()
            ->printer_id->toBe($job->location->printers()->value('id'));
    });

    it('queues a paid job in auto_if_paid mode', function () {
        $job = quotedJobAt(PrintMode::AutoIfPaid, ['paid_at' => now()]);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh()->status)->toBe(PrintJobStatus::Queued);
    });

    it('leaves an unpaid job ready in auto_if_paid mode', function () {
        $job = quotedJobAt(PrintMode::AutoIfPaid);

        app(PrintJobService::class)->confirm($job);

        expect($job->fresh()->status)->toBe(PrintJobStatus::Ready);
    });
});

describe('markPaid', function () {
    it('queues a ready job in auto_if_paid mode', function () {
        $job = quotedJobAt(PrintMode::AutoIfPaid, ['status' => PrintJobStatus::Ready]);

        app(PrintJobService::class)->markPaid($job);

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Queued)
            ->paid_at->not->toBeNull();
    });

    it('does not queue a ready job in manual mode', function () {
        $job = quotedJobAt(PrintMode::Manual, ['status' => PrintJobStatus::Ready]);

        app(PrintJobService::class)->markPaid($job);

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Ready)
            ->paid_at->not->toBeNull();
    });
});

describe('queue', function () {
    it('skips disabled printers when assigning one', function () {
        $location = Location::factory()->create();
        Printer::factory()->disabled()->create(['location_id' => $location->id]);
        $active = Printer::factory()->create(['location_id' => $location->id]);
        $job = PrintJob::factory()->status(PrintJobStatus::Ready)->create(['location_id' => $location->id]);

        app(PrintJobService::class)->queue($job);

        expect($job->fresh()->printer_id)->toBe($active->id);
    });

    it('fails when the location has no active printer', function () {
        $job = PrintJob::factory()->status(PrintJobStatus::Ready)->create();

        app(PrintJobService::class)->queue($job);
    })->throws(RuntimeException::class, 'has no active printer');
});

describe('transition', function () {
    it('rejects a transition that is not allowed and keeps the status', function () {
        $job = PrintJob::factory()->status(PrintJobStatus::Ready)->create();

        expect(fn () => app(PrintJobService::class)->transition($job, PrintJobStatus::Printed))
            ->toThrow(InvalidStatusTransition::class);
        expect($job->fresh()->status)->toBe(PrintJobStatus::Ready);
    });

    it('rejects a transition when the stored status changed since the job was loaded', function () {
        $job = PrintJob::factory()->status(PrintJobStatus::Queued)->create();
        $stale = PrintJob::find($job->id);
        app(PrintJobService::class)->transition($job, PrintJobStatus::Printing);

        app(PrintJobService::class)->transition($stale, PrintJobStatus::Printing);
    })->throws(InvalidStatusTransition::class);
});

describe('expire', function () {
    it('expires the job and deletes its files', function () {
        Storage::fake('local');
        Storage::disk('local')->put('originals/a.docx', 'doc');
        Storage::disk('local')->put('print-jobs/a.pdf', 'pdf');
        $job = PrintJob::factory()->status(PrintJobStatus::Ready)
            ->create(['original_path' => 'originals/a.docx', 'pdf_path' => 'print-jobs/a.pdf']);

        app(PrintJobService::class)->expire($job);

        expect($job->fresh()->status)->toBe(PrintJobStatus::Expired);
        Storage::disk('local')->assertMissing(['originals/a.docx', 'print-jobs/a.pdf']);
    });
});

describe('submitForCustomer', function () {
    beforeEach(fn () => Storage::fake('local'));

    it('stores the PDF and leaves the job ready with a phone folio in manual mode', function () {
        $location = Location::factory()->printMode(PrintMode::Manual)->create();
        Printer::factory()->create(['location_id' => $location->id]);
        $customer = Customer::factory()->create(['phone' => '7441234521']);

        $job = app(PrintJobService::class)->submitForCustomer(
            $location, $customer, '%PDF-1.4', 'tarea.pdf', Channel::Web, ['copies' => 3, 'duplex' => Duplex::Long],
        );

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Ready)
            ->customer_id->toBe($customer->id)
            ->folio->toBe("{$job->id}-4521")
            ->copies->toBe(3)
            ->duplex->toBe(Duplex::Long)
            ->printer_id->toBeNull();
        Storage::disk('local')->assertExists($job->pdf_path);
    });

    it('queues the job right away in auto mode', function () {
        $location = Location::factory()->printMode(PrintMode::Auto)->create();
        $printer = Printer::factory()->create(['location_id' => $location->id]);

        $job = app(PrintJobService::class)->submitForCustomer(
            $location, Customer::factory()->create(), '%PDF-1.4', 'tarea.pdf', Channel::Web,
        );

        expect($job->fresh())
            ->status->toBe(PrintJobStatus::Queued)
            ->printer_id->toBe($printer->id);
    });
});
