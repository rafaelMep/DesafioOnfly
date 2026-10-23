<?php

namespace Database\Factories;

use App\Enums\TravelStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'destination' => fake()->city(),
            'departure_date' => now()->addDays(7)->toDateString(),
            'return_date' => now()->addDays(10)->toDateString(),
            'status' => TravelStatus::Requested,
        ];
    }
}
