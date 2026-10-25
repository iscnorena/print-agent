<?php

namespace App\Http\Controllers\Operator;

use App\Enums\Channel;
use App\Enums\PrinterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StorePrintJobRequest;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrintJobController extends Controller
{
    /**
     * Upload form plus the latest jobs.
     */
    public function index(): View
    {
        return view('operator.print-jobs.index', [
            'printers' => Printer::query()->where('status', PrinterStatus::Active)->orderBy('name')->get(),
            'jobs' => $this->recentJobs(),
        ]);
    }

    /**
     * Just the jobs table; the page polls it to show status changes live.
     */
    public function list(): View
    {
        return view('operator.print-jobs._list', ['jobs' => $this->recentJobs()]);
    }

    public function store(StorePrintJobRequest $request, PrintJobService $service): RedirectResponse
    {
        $printer = $request->printer();
        $file = $request->file('file');

        $job = $service->queueDirect(
            $printer,
            $file->getContent(),
            $file->getClientOriginalName(),
            Channel::Web,
            $request->printOptions(),
        );

        return redirect()
            ->route('operator.print-jobs.index')
            ->with('status', "Folio #{$job->folio} enviado a {$printer->name}.");
    }

    /**
     * @return Collection<int, PrintJob>
     */
    private function recentJobs(): Collection
    {
        return PrintJob::query()->with('printer')->latest('id')->limit(15)->get();
    }
}
