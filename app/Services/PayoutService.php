<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\User;
use App\Repositories\EarningRepository;
use App\Repositories\PayoutRepository;
use App\Repositories\WalletRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public const MINIMUM_PAYOUT_AMOUNT = 30.00;

    protected EarningRepository $earningRepo;
    protected PayoutRepository $payoutRepo;
    protected WalletRepository $walletRepo;

    public function __construct(
        EarningRepository $earningRepo,
        PayoutRepository $payoutRepo,
        WalletRepository $walletRepo
    ) {
        $this->earningRepo = $earningRepo;
        $this->payoutRepo = $payoutRepo;
        $this->walletRepo = $walletRepo;
    }

    /**
     * Get current earnings since last completed payout
     */
    public function getCurrentEarnings(int $userId): float
    {
        return $this->earningRepo->getCurrentEarnings($userId);
    }

    /**
     * Check if user can request a payout
     */
    public function canRequestPayout(int $userId): array
    {
        $currentEarnings = $this->getCurrentEarnings($userId);
        $hasPendingPayout = $this->payoutRepo->hasPendingPayout($userId);

        if ($hasPendingPayout) {
            return [
                'can_request' => false,
                'reason' => 'You already have a pending payout request.',
            ];
        }

        if ($currentEarnings < self::MINIMUM_PAYOUT_AMOUNT) {
            return [
                'can_request' => false,
                'reason' => sprintf(
                    'Payouts require a minimum of $%.2f. Your current earnings are $%.2f.',
                    self::MINIMUM_PAYOUT_AMOUNT,
                    $currentEarnings
                ),
            ];
        }

        return [
            'can_request' => true,
            'reason' => null,
        ];
    }

    /**
     * Request a payout for a user
     */
    public function requestPayout(User $user, ?int $walletId = null): array
    {
        $canRequest = $this->canRequestPayout($user->id);

        if (!$canRequest['can_request']) {
            return [
                'success' => false,
                'message' => $canRequest['reason'],
            ];
        }

        // Get wallet - either specified or primary
        if ($walletId) {
            $wallet = $this->walletRepo->findByUserAndId($user->id, $walletId);
            if (!$wallet) {
                return [
                    'success' => false,
                    'message' => 'Wallet not found.',
                ];
            }
        } else {
            $wallet = $this->walletRepo->getPrimaryWallet($user->id);
        }

        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'Please set up your wallet address before requesting a payout.',
            ];
        }

        $currentEarnings = $this->getCurrentEarnings($user->id);

        $payout = DB::transaction(function () use ($user, $currentEarnings, $wallet) {
            return $this->payoutRepo->create([
                'user_id' => $user->id,
                'amount' => $currentEarnings,
                'wallet_address' => $wallet->wallet_address,
                'wallet_type' => $wallet->wallet_type,
                'status' => Payout::STATUS_PENDING,
            ]);
        });

        return [
            'success' => true,
            'message' => 'Payout request submitted successfully.',
            'payout' => $payout,
        ];
    }

    /**
     * Get payout history for a user
     */
    public function getPayoutHistory(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->payoutRepo->getByUserId($userId, $perPage);
    }

    /**
     * Get total historical payouts amount
     */
    public function getTotalPayoutsAmount(int $userId): float
    {
        return $this->payoutRepo->getTotalPayoutsAmount($userId);
    }

    /**
     * Get payout by ID (with user ownership check)
     */
    public function getPayoutForUser(int $payoutId, int $userId): ?Payout
    {
        $payout = $this->payoutRepo->findById($payoutId);

        if ($payout && $payout->user_id === $userId) {
            return $payout;
        }

        return null;
    }
}
