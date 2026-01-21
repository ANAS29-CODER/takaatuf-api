<?php

namespace App\Repositories;

use App\Models\Payout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PayoutRepository
{
    /**
     * Get all payouts for a user (ordered by created_at desc)
     */
    public function getByUserId(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Payout::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all payouts for a user without pagination
     */
    public function getAllByUserId(int $userId): Collection
    {
        return Payout::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the last completed payout for a user
     */
    public function getLastCompletedPayout(int $userId): ?Payout
    {
        return Payout::where('user_id', $userId)
            ->where('status', Payout::STATUS_COMPLETED)
            ->orderBy('payout_at', 'desc')
            ->first();
    }

    /**
     * Get total historical payouts amount for a user
     */
    public function getTotalPayoutsAmount(int $userId): float
    {
        return (float) Payout::where('user_id', $userId)
            ->where('status', Payout::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Create a new payout request
     */
    public function create(array $data): Payout
    {
        return Payout::create($data);
    }

    /**
     * Update payout status
     */
    public function updateStatus(int $payoutId, string $status, ?string $notes = null): Payout
    {
        $payout = Payout::findOrFail($payoutId);

        $payout->status = $status;

        if ($notes !== null) {
            $payout->admin_notes = $notes;
        }

        if (in_array($status, [Payout::STATUS_COMPLETED, Payout::STATUS_FAILED])) {
            $payout->payout_at = now();
        }

        $payout->save();

        return $payout;
    }

    /**
     * Find a payout by ID
     */
    public function findById(int $payoutId): ?Payout
    {
        return Payout::find($payoutId);
    }

    /**
     * Check if user has a pending payout request
     */
    public function hasPendingPayout(int $userId): bool
    {
        return Payout::where('user_id', $userId)
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_APPROVED])
            ->exists();
    }
}
