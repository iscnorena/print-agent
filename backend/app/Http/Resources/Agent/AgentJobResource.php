<?php

namespace App\Http\Resources\Agent;

use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the agent needs to print a job. Keys match the agent's PrintOptions.
 *
 * @mixin PrintJob
 */
class AgentJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'status' => $this->status->value,
            'copies' => $this->copies,
            'color' => $this->color,
            'duplex' => $this->duplex->value,
            'page_range' => $this->page_range,
            'total_pages' => $this->total_pages,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'file_url' => route('agent.jobs.file', $this->resource),
        ];
    }
}
