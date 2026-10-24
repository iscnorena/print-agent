<?php

namespace Database\Factories;

use App\Enums\PrintMode;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'is_active' => true,
            'print_mode' => PrintMode::Manual,
        ];
    }

    public function printMode(PrintMode $printMode): static
    {
        return $this->state(fn (array $attributes) => ['print_mode' => $printMode]);
    }
}
