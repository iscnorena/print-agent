<?php

namespace Database\Factories;

use App\Enums\PrinterStatus;
use App\Models\Location;
use App\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Brother DCP-T720DW',
            'agent_token_hash' => Printer::hashToken(Str::random(48)),
            'supports_color' => true,
            'status' => PrinterStatus::Active,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PrinterStatus::Disabled]);
    }

    /**
     * Use a known plain token so tests can authenticate as this printer.
     */
    public function withToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes) => ['agent_token_hash' => Printer::hashToken($plainToken)]);
    }
}
