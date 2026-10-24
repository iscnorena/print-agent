<?php

namespace App\Exceptions;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Exception;
use Illuminate\Http\JsonResponse;

class InvalidStatusTransition extends Exception
{
    public function __construct(public readonly PrintJob $job, public readonly PrintJobStatus $to)
    {
        parent::__construct("Print job {$job->id} cannot move from {$job->status->value} to {$to->value}.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'current_status' => $this->job->status->value,
        ], 409);
    }
}
