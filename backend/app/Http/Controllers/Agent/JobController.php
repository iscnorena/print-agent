<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateJobStatusRequest;
use App\Http\Resources\Agent\AgentJobResource;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobController extends Controller
{
    /**
     * Next queued job for the calling printer, or 204 when there is none.
     */
    public function next(Request $request): AgentJobResource|Response
    {
        $job = PrintJob::queuedFor($this->printer($request))->first();

        return $job ? new AgentJobResource($job) : response()->noContent();
    }

    /**
     * Stream the job's normalized PDF.
     */
    public function file(Request $request, int $job): StreamedResponse
    {
        $printJob = $this->findForPrinter($request, $job);

        abort_unless(in_array($printJob->status, [PrintJobStatus::Queued, PrintJobStatus::Printing], true), 404);

        $disk = Storage::disk(config('printing.disk'));
        abort_unless($printJob->pdf_path && $disk->exists($printJob->pdf_path), 404, 'File not found.');

        return $disk->download($printJob->pdf_path, "job-{$printJob->id}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Agent reports printing / printed / failed.
     *
     * Repeating the job's current status is accepted, so an agent can safely
     * retry a report whose response it never received.
     */
    public function updateStatus(UpdateJobStatusRequest $request, PrintJobService $service, int $job): AgentJobResource
    {
        $printJob = $this->findForPrinter($request, $job);
        $status = $request->status();

        if ($printJob->status !== $status) {
            $service->transition($printJob, $status, $request->validated('error'));
        }

        return new AgentJobResource($printJob);
    }

    private function printer(Request $request): Printer
    {
        return $request->attributes->get('printer');
    }

    /**
     * Jobs of other printers are reported as missing, not forbidden.
     */
    private function findForPrinter(Request $request, int $jobId): PrintJob
    {
        return PrintJob::query()->whereBelongsTo($this->printer($request))->findOrFail($jobId);
    }
}
