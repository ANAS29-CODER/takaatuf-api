<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profile_completed' => false,
            'role' => 'kr',
            'city_neighborhood' => null,
            'wallet_type' => null,
            'wallet_address' => null,
            'paypal_account' => null,
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
     * Create a Knowledge Provider user.
     */
    public function knowledgeProvider(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Knowledge Provider',
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x' . Str::random(40),
            'profile_completed' => true,
            'city_neighborhood' => 'Gaza City',
        ]);
    }

    /**
     * Create a Knowledge Requester user.
     */
    public function knowledgeRequester(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Knowledge Requester',
            'paypal_account' => fake()->safeEmail(),
            'profile_completed' => true,
            'city_neighborhood' => fake()->city(),
        ]);
    }
}
