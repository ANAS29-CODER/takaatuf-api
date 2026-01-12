<?php

namespace App\Services;

use App\Models\Wallet;
use App\Repositories\WalletRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected WalletRepository $walletRepo;

    // Validation patterns for wallet addresses
    protected array $validationPatterns = [
        'ethereum' => '/^0x[a-fA-F0-9]{40}$/',
        'solana' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
        'bitcoin' => '/^(1|3)[a-km-zA-HJ-NP-Z1-9]{25,34}$|^bc1[a-z0-9]{39,59}$/',
    ];

    public function __construct(WalletRepository $walletRepo)
    {
        $this->walletRepo = $walletRepo;
    }

    /**
     * Validate wallet address format
     */
    public function validateWalletAddress(string $type, string $address): array
    {
        if (!preg_match($this->validationPatterns[$type], $address)) {
            $typeLabels = [
                'ethereum' => 'Ethereum (must start with 0x followed by 40 hex characters)',
                'solana' => 'Solana (32-44 base58 characters)',
                'bitcoin' => 'Bitcoin (starts with 1, 3, or bc1)',
            ];

            return [
                'valid' => false,
                'message' => sprintf('Invalid %s address format.', $typeLabels[$type]),
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }


    public function getUserWallets(int $userId): Collection
    {
        return $this->walletRepo->getByUserId($userId);
    }


    public function getPrimaryWallet(int $userId): ?Wallet
    {
        return $this->walletRepo->getPrimaryWallet($userId);
    }


    public function addWallet(int $userId, string $walletType, string $walletAddress, bool $isPrimary = false): array
    {
        // Validate address format
        $validation = $this->validateWalletAddress($walletType, $walletAddress);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        if ($this->walletRepo->addressExistsForUser($userId, $walletAddress)) {
            return [
                'success' => false,
                'message' => 'This wallet address is already registered to your account.',
            ];
        }

        return DB::transaction(function () use ($userId, $walletType, $walletAddress, $isPrimary) {

            $isFirstWallet = $this->walletRepo->countByUserId($userId) === 0;
            $shouldBePrimary = $isPrimary || $isFirstWallet;

            if ($shouldBePrimary) {
                Wallet::where('user_id', $userId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $wallet = $this->walletRepo->create([
                'user_id' => $userId,
                'wallet_type' => $walletType,
                'wallet_address' => $walletAddress,
                'is_primary' => $shouldBePrimary,
            ]);

            return [
                'success' => true,
                'message' => 'Wallet added successfully.',
                'wallet' => $wallet,
            ];
        });
    }

    /**
     * Update a wallet
     */
    public function updateWallet(int $userId, int $walletId, string $walletType, string $walletAddress): array
    {
        $wallet = $this->walletRepo->findByUserAndId($userId, $walletId);

        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'Wallet not found.',
            ];
        }

        $validation = $this->validateWalletAddress($walletType, $walletAddress);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        // Check if address already exists for this user (excluding current wallet)
        if ($this->walletRepo->addressExistsForUser($userId, $walletAddress, $walletId)) {
            return [
                'success' => false,
                'message' => 'This wallet address is already registered to your account.',
            ];
        }

        $wallet = $this->walletRepo->update($walletId, [
            'wallet_type' => $walletType,
            'wallet_address' => $walletAddress,
        ]);

        return [
            'success' => true,
            'message' => 'Wallet updated successfully.',
            'wallet' => $wallet,
        ];
    }

    /**
     * Delete a wallet
     */
    public function deleteWallet(int $userId, int $walletId): array
    {
        $wallet = $this->walletRepo->findByUserAndId($userId, $walletId);

        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'Wallet not found.',
            ];
        }

        $wasPrimary = $wallet->is_primary;

        $this->walletRepo->delete($walletId);

        // If deleted wallet was primary, set another wallet as primary
        if ($wasPrimary) {
            $remainingWallet = Wallet::where('user_id', $userId)->first();
            if ($remainingWallet) {
                $remainingWallet->is_primary = true;
                $remainingWallet->save();
            }
        }

        return [
            'success' => true,
            'message' => 'Wallet deleted successfully.',
        ];
    }

    /**
     * Set a wallet as primary
     */
    public function setPrimaryWallet(int $userId, int $walletId): array
    {
        $wallet = $this->walletRepo->findByUserAndId($userId, $walletId);

        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'Wallet not found.',
            ];
        }

        if ($wallet->is_primary) {
            return [
                'success' => true,
                'message' => 'This wallet is already your primary wallet.',
                'wallet' => $wallet,
            ];
        }

        $wallet = $this->walletRepo->setPrimary($userId, $walletId);

        return [
            'success' => true,
            'message' => 'Primary wallet updated successfully.',
            'wallet' => $wallet,
        ];
    }

    /**
     * Get wallet by ID for user
     */
    public function getWalletForUser(int $userId, int $walletId): ?Wallet
    {
        return $this->walletRepo->findByUserAndId($userId, $walletId);
    }
}
