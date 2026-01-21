<?php

namespace App\Repositories;

use App\Models\Earning;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Collection;

class EarningRepository
{
    /**
     * Get all earnings for a user
     */
    public function getByUserId(int $userId): Collection
    {
        return Earning::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get current earnings since last completed payout
     */
    public function getCurrentEarnings(int $userId): float
    {
        $lastCompletedPayout = Payout::where('user_id', $userId)
            ->where('status', Payout::STATUS_COMPLETED)
            ->orderBy('created_at', 'desc')
            ->first();

        $query = Earning::where('user_id', $userId);

        if ($lastCompletedPayout) {
            $query->where('created_at', '>', $lastCompletedPayout->created_at);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Create a new earning record
     */
    public function create(array $data): Earning
    {
        return Earning::create($data);
    }
}
