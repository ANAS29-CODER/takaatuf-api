<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['ethereum', 'solana', 'bitcoin']);

        return [
            'user_id' => User::factory(),
            'wallet_type' => $type,
            'wallet_address' => $this->generateWalletAddress($type),
            'is_primary' => false,
        ];
    }

    /**
     * Generate a valid wallet address for the given type
     */
    protected function generateWalletAddress(string $type): string
    {
        return match ($type) {
            'ethereum' => '0x' . Str::random(40),
            'solana' => Str::random(44),
            'bitcoin' => '1' . Str::random(33),
            default => Str::random(40),
        };
    }

    /**
     * Set the wallet as primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Set the wallet type to ethereum.
     */
    public function ethereum(): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x' . Str::random(40),
        ]);
    }

    /**
     * Set the wallet type to solana.
     */
    public function solana(): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_type' => 'solana',
            'wallet_address' => Str::random(44),
        ]);
    }

    /**
     * Set the wallet type to bitcoin.
     */
    public function bitcoin(): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_type' => 'bitcoin',
            'wallet_address' => '1' . Str::random(33),
        ]);
    }
}
