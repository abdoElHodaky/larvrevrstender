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
            'phone' => '+966' . fake()->numerify('5########'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => Hash::make('password'),
            'type' => fake()->randomElement(['customer', 'merchant', 'admin']),
            'status' => fake()->randomElement(['active', 'inactive', 'suspended', 'banned']),
            'google_id' => null,
            'facebook_id' => null,
            'twitter_id' => null,
            'github_id' => null,
            'avatar' => null,
            'provider' => null,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'login_count' => 0,
            'metadata' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be a customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => User::TYPE_CUSTOMER,
        ]);
    }

    /**
     * Indicate that the user should be a merchant.
     */
    public function merchant(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => User::TYPE_MERCHANT,
        ]);
    }

    /**
     * Indicate that the user should be an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => User::TYPE_ADMIN,
        ]);
    }

    /**
     * Indicate that the user should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * Indicate that the user should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    /**
     * Indicate that the user should be suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_SUSPENDED,
        ]);
    }

    /**
     * Indicate that the user should be banned.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_BANNED,
        ]);
    }
}
