<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'           => fake()->uuid(),
            'name'         => fake()->name(),
            'email'        => fake()->unique()->safeEmail(),
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'username'     => fake()->unique()->userName(),
            'is_active'    => true,
        ];
    }
}
