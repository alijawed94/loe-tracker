<?php

namespace Database\Factories;

use App\Models\LoeFeedback;
use App\Models\LoeReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoeFeedback>
 */
class LoeFeedbackFactory extends Factory
{
    protected $model = LoeFeedback::class;

    public function definition(): array
    {
        return [
            'loe_report_id' => LoeReport::factory(),
            'user_id' => User::factory(),
            'message' => fake()->sentence(),
        ];
    }
}
