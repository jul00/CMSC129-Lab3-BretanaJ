<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MovieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'genre' => fake()->randomElement(['Action','Drama','Comedy','Horror','Sci-Fi']),
            'release_year' => fake()->numberBetween(1990, 2025),
            'rating' => fake()->randomFloat(1, 1, 10),
            'comment' => fake()->paragraph(),
            'watched_at' => fake()->date(),
            'category_id' => 1, // or random later
        ];
    }
}
