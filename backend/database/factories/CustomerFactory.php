<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '744'.fake()->unique()->numerify('#######');

        return [
            'wa_id' => '521'.$phone,
            'phone' => $phone,
            'profile_name' => fake()->firstName(),
        ];
    }
}
