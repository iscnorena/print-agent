<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\PrintOptionsRequest;
use App\Models\PrintJob;

/**
 * The operator's "Imprimir" button on a job waiting in "ready". The customer's
 * options come prefilled and the operator may adjust them before printing.
 */
class QueuePrintJobRequest extends PrintOptionsRequest
{
    /**
     * Errors are shown above the jobs table, apart from the upload form's.
     *
     * @var string
     */
    protected $errorBag = 'queue';

    /**
     * Any logged-in operator may print; the route requires auth.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function supportsColor(): bool
    {
        /** @var PrintJob $job */
        $job = $this->route('job');

        return ($job->printer ?? $job->location->defaultPrinter())?->supports_color ?? false;
    }
}
