<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    /**
     * Mark the calling printer's agent as alive.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Printer $printer */
        $printer = $request->attributes->get('printer');
        $printer->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['server_time' => now()->toIso8601String()]);
    }
}
