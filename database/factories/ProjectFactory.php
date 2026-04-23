<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' '.fake()->unique()->word(),
            'engagement' => fake()->company(),
            'description' => fake()->sentence(),
            'engagement_type' => fake()->randomElement(['project', 'product', 'marketing', 'admin']),
            'status' => true,
        ];
    }
}
