<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => Channel::WhatsApp,
            'location_id' => Location::factory(),
            'customer_id' => Customer::factory(),
            'original_name' => 'documento.pdf',
            'pdf_path' => 'print-jobs/'.Str::uuid().'.pdf',
            'total_pages' => 3,
            'copies' => 1,
            'color' => false,
            'duplex' => Duplex::Simplex,
            'price' => 6,
            'status' => PrintJobStatus::Quoted,
        ];
    }

    /**
     * A job waiting in the printer's queue.
     */
    public function queuedOn(Printer $printer): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $printer->location_id,
            'printer_id' => $printer->id,
            'status' => PrintJobStatus::Queued,
            'queued_at' => now(),
        ]);
    }

    public function status(PrintJobStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
