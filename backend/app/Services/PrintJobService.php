<?php

namespace App\Services;

use App\Enums\PrinterStatus;
use App\Enums\PrintJobStatus;
use App\Enums\PrintMode;
use App\Exceptions\InvalidStatusTransition;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
