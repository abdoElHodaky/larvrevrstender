<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+966' . fake()->numerify('#########'), // Saudi phone format
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // Default password
            'remember_token' => Str::random(10),
            'user_type' => fake()->randomElement(['individual', 'business']),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the user should be a business user.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'business',
        ]);
    }

    /**
     * Indicate that the user should be an individual user.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'individual',
        ]);
    }
}
