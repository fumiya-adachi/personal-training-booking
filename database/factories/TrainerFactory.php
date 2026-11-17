<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrainerFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->firstName(), 'specialty' => 'ボディメイク', 'bio' => '一人ひとりに合わせたトレーニングをサポートします。', 'color' => '#D7B9A4', 'is_active' => true];
    }
}
