<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrinterStatus;
use App\Enums\PrintJobStatus;
use App\Enums\PrintMode;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only place where print job statuses change. Every channel (WhatsApp,
 * web, app, kiosk) and the agent API go through here.
 */
class PrintJobService
{
    /**
     * Move a job to a new status, enforcing the allowed transitions.
     *
     * The row is locked so two concurrent requests (e.g. an agent retrying
     * "printing") can never both win the same transition.
     *
     * @throws InvalidStatusTransition
     */
    public function transition(PrintJob $job, PrintJobStatus $to, ?string $errorMessage = null): PrintJob
    {
        return DB::transaction(function () use ($job, $to, $errorMessage): PrintJob {
            $locked = PrintJob::query()->lockForUpdate()->findOrFail($job->id);

            if (! $locked->status->canTransitionTo($to)) {
                throw new InvalidStatusTransition($locked, $to);
            }

            $locked->status = $to;
            $locked->error_message = $to === PrintJobStatus::Failed ? $errorMessage : null;

            $timestampColumn = match ($to) {
                PrintJobStatus::Confirmed => 'confirmed_at',
                PrintJobStatus::Ready => 'ready_at',
                PrintJobStatus::Queued => 'queued_at',
                PrintJobStatus::Printed => 'printed_at',
                default => null,
            };

            if ($timestampColumn !== null) {
                $locked->{$timestampColumn} = now();
            }

            $locked->save();

            return $job->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Store a PDF and send it straight to a printer, skipping the customer flow
     * (operator upload, test command). The operator is present, so print_mode does not apply.
     *
     * @param  array{copies?: int, color?: bool, duplex?: Duplex, page_range?: ?string}  $options
     */
    public function queueDirect(Printer $printer, string $pdfContents, string $originalName, Channel $channel, array $options = []): PrintJob
    {
        $pdfPath = 'print-jobs/'.Str::uuid().'.pdf';
        Storage::disk(config('printing.disk'))->put($pdfPath, $pdfContents);

        $job = new PrintJob([
            'channel' => $channel,
            'location_id' => $printer->location_id,
            'printer_id' => $printer->id,
            'original_name' => $originalName,
            'pdf_path' => $pdfPath,
            'page_range' => $options['page_range'] ?? null,
            'copies' => max(1, $options['copies'] ?? 1),
            'color' => $options['color'] ?? false,
            'duplex' => $options['duplex'] ?? Duplex::Simplex,
        ]);
        $job->status = PrintJobStatus::Confirmed;
        $job->save();
        $job->folio = (string) $job->id;
        $job->save();

        return $this->queue($job);
    }

    /**
     * Customer accepted the quote: assign a folio and apply the location's print mode.
     */
    public function confirm(PrintJob $job): PrintJob
    {
        $this->transition($job, PrintJobStatus::Confirmed);

        $job->folio ??= (string) $job->id;
        $job->save();

        $queueNow = match ($job->location->print_mode) {
            PrintMode::Manual => false,
            PrintMode::AutoIfPaid => $job->paid_at !== null,
            PrintMode::Auto => true,
        };

        return $queueNow ? $this->queue($job) : $this->transition($job, PrintJobStatus::Ready);
    }

    /**
     * Record payment. With "auto_if_paid" a job waiting in "ready" is queued right away.
     */
    public function markPaid(PrintJob $job): PrintJob
    {
        $job->paid_at ??= now();
        $job->save();

        if ($job->status === PrintJobStatus::Ready && $job->location->print_mode === PrintMode::AutoIfPaid) {
            return $this->queue($job);
        }

        return $job;
    }

    /**
     * Send a job to its printer. Used by auto modes and by the operator's "Imprimir" button.
     */
    public function queue(PrintJob $job): PrintJob
    {
        if ($job->printer_id === null) {
            $printerId = $job->location->printers()
                ->where('status', PrinterStatus::Active)
                ->orderBy('id')
                ->value('id');

            if ($printerId === null) {
                throw new RuntimeException("Location {$job->location_id} has no active printer.");
            }

            $job->printer_id = $printerId;
            $job->save();
        }

        return $this->transition($job, PrintJobStatus::Queued);
    }

    /**
     * Expire a job that was never picked up and delete its files.
     */
    public function expire(PrintJob $job): PrintJob
    {
        $this->transition($job, PrintJobStatus::Expired);

        $paths = array_filter([$job->original_path, $job->pdf_path]);
        Storage::disk(config('printing.disk'))->delete($paths);

        return $job;
    }
}
