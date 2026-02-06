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
            'phone' => '+966'.fake()->numerify('#########'), // Saudi phone format
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // Default password
            'remember_token' => Str::random(10),
            'type' => fake()->randomElement(['customer', 'merchant', 'admin']),
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
     * Indicate that the user should be a merchant user.
     */
    public function merchant(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'merchant',
        ]);
    }

    /**
<<<<<<< HEAD
     * Indicate that the user should be an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'admin',
        ]);
    }

    /**
     * Indicate that the user should be a customer user.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
=======
     * Indicate that the user should be a customer user.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
>>>>>>> origin/feature/laravel-session-management
            'type' => 'customer',
        ]);
    }
}
