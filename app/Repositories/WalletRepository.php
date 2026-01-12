<?php

namespace App\Repositories;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WalletRepository
{

    public function getByUserId(int $userId): Collection
    {
        return Wallet::where('user_id', $userId)->get();
    }


    public function getPrimaryWallet(int $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->where('is_primary', true)
            ->first();
    }


    public function findById(int $walletId): ?Wallet
    {
        return Wallet::find($walletId);
    }


    public function findByUserAndId(int $userId, int $walletId): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->where('id', $walletId)
            ->first();
    }


    public function addressExistsForUser(int $userId, string $address, ?int $excludeId = null): bool
    {
        $query = Wallet::where('user_id', $userId)
            ->where('wallet_address', $address);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Create a new wallet
     */
    public function create(array $data): Wallet
    {
        return Wallet::create($data);
    }


    public function update(int $walletId, array $data): Wallet
    {
        $wallet = Wallet::findOrFail($walletId);
        $wallet->update($data);
        return $wallet->fresh();
    }


    public function delete(int $walletId): bool
    {
        return Wallet::destroy($walletId) > 0;
    }


    public function setPrimary(int $userId, int $walletId): Wallet
    {

        DB::transaction(function () use ($userId, $walletId) {
            Wallet::where('user_id', $userId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

           $wallet = Wallet::where('id', $walletId)
                ->where('user_id', $userId)
                ->update(['is_primary' => true]);

         return $wallet;
        });

        return response()->json([
            'message' => 'Something went wrong while setting the primary wallet.',
        ]);



    }

    public function countByUserId(int $userId): int
    {
        return Wallet::where('user_id', $userId)->count();
    }
}
