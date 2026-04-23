<?php

namespace Database\Factories;

use App\Models\LoeReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoeReport>
 */
class LoeReportFactory extends Factory
{
    protected $model = LoeReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'month' => (int) fake()->numberBetween(1, 12),
            'year' => (int) fake()->numberBetween(2024, 2030),
            'total_percentage' => fake()->randomFloat(2, 1, 100),
            'submitted_at' => now(),
        ];
    }
}
