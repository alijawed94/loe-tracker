<?php

namespace Database\Factories;

use App\Models\LoeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoeEntry>
 */
class LoeEntryFactory extends Factory
{
    protected $model = LoeEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loe_report_id' => \App\Models\LoeReport::factory(),
            'project_id' => \App\Models\Project::factory(),
            'percentage' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
