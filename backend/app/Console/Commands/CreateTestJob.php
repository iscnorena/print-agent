<?php

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('job:create-test
    {pdf : Path to a local PDF file}
    {--printer= : Printer id (defaults to the first printer)}
    {--copies=1}
    {--color : Print in color}
    {--duplex=simplex : simplex, long or short}
    {--pages= : Page range, e.g. "1-3,5"}')]
#[Description('Create a queued print job from a local PDF, skipping the customer flow')]
class CreateTestJob extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrintJobService $service): int
    {
        $source = $this->argument('pdf');
        if (! is_file($source) || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error("Not a PDF file: {$source}");

            return self::FAILURE;
        }

        $printer = $this->option('printer')
            ? Printer::find($this->option('printer'))
            : Printer::query()->orderBy('id')->first();
        if ($printer === null) {
            $this->error('Printer not found. Create one with: php artisan printer:create "<name>"');

            return self::FAILURE;
        }

        $duplex = Duplex::tryFrom($this->option('duplex'));
        if ($duplex === null) {
            $this->error('--duplex must be simplex, long or short.');

            return self::FAILURE;
        }

        $color = (bool) $this->option('color');
        if ($color && ! $printer->supports_color) {
            $this->error("Printer \"{$printer->name}\" does not support color.");

            return self::FAILURE;
        }

        $pdfPath = 'print-jobs/'.Str::uuid().'.pdf';
        Storage::disk(config('printing.disk'))->put($pdfPath, file_get_contents($source));

        $job = new PrintJob([
            'channel' => Channel::Web,
            'location_id' => $printer->location_id,
            'printer_id' => $printer->id,
            'original_name' => basename($source),
            'pdf_path' => $pdfPath,
            'page_range' => $this->option('pages'),
            'copies' => max(1, (int) $this->option('copies')),
            'color' => $color,
            'duplex' => $duplex,
        ]);
        $job->status = PrintJobStatus::Confirmed;
        $job->save();
        $job->folio = (string) $job->id;
        $job->save();

        $service->queue($job);

        $this->info("Job #{$job->id} queued on printer \"{$printer->name}\".");

        return self::SUCCESS;
    }
}
