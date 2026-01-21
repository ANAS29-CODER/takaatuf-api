<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 30, 500),
            'wallet_address' => '0x' . Str::random(40),
            'wallet_type' => fake()->randomElement(['ethereum', 'solana', 'bitcoin']),
            'status' => Payout::STATUS_PENDING,
            'admin_notes' => null,
            'payout_at' => null,
        ];
    }

    /**
     * Set the payout status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payout::STATUS_PENDING,
            'payout_at' => null,
        ]);
    }

    /**
     * Set the payout status to approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payout::STATUS_APPROVED,
            'payout_at' => null,
        ]);
    }

    /**
     * Set the payout status to completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payout::STATUS_COMPLETED,
            'payout_at' => now(),
        ]);
    }

    /**
     * Set the payout status to rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payout::STATUS_REJECTED,
            'payout_at' => now(),
            'admin_notes' => fake()->sentence(),
        ]);
    }

    /**
     * Set the payout status to failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payout::STATUS_FAILED,
            'payout_at' => now(),
            'admin_notes' => fake()->sentence(),
        ]);
    }
}
