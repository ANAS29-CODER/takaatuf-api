<?php

namespace App\Services;

use App\Models\KnowledgeRequest;
use App\Models\User;
use App\Models\UserKnowledgeRequest;
use App\Repositories\EarningRepository;
use App\Repositories\KnowledgeProviderRepository;
use Illuminate\Support\Collection;

class KnowledgeProviderDashboardService
{
    protected EarningRepository $earningRepo;
    protected KnowledgeProviderRepository $kpRepo;

    public function __construct(
        EarningRepository $earningRepo,
        KnowledgeProviderRepository $kpRepo
    ) {
        $this->earningRepo = $earningRepo;
        $this->kpRepo = $kpRepo;
    }

    /**
     * Get the complete dashboard data for a Knowledge Provider
     */
    public function getDashboardData(User $user): array
    {
        $earnings = $this->getEarningsSummary($user->id);
        $activeRequests = $this->getActiveRequests($user);
        $availableRequests = $this->getAvailableRequests($user);
        $completedRequests = $this->getCompletedRequests($user);

        $hasActiveRequests = $activeRequests->isNotEmpty();

        return [
            'earnings_summary' => $earnings,
            'has_active_requests' => $hasActiveRequests,
            'active_requests' => $activeRequests,
            'available_requests' => $availableRequests,
            'completed_requests' => [
                'total_count' => $completedRequests->count(),
                'items' => $completedRequests,
            ],
        ];
    }

    /**
     * Get earnings summary since last payout
     */
    public function getEarningsSummary(int $userId): array
    {
        $currentEarnings = $this->earningRepo->getCurrentEarnings($userId);

        return [
            'amount' => $currentEarnings,
            'formatted' => sprintf('$%.2f', $currentEarnings),
            'currency' => 'USD',
        ];
    }

    /**
     * Get active requests for the KP
     * Includes requests that are assigned to them or accepted by them and in progress
     * Ordered by most recently updated first
     */
    public function getActiveRequests(User $user): Collection
    {
        return $this->kpRepo->getActiveRequestsForKP($user->id);
    }

    /**
     * Get available requests for the KP
     * Requests that the KP is eligible to accept and not currently assigned to them
     * Ordered by most recently created first
     */
    public function getAvailableRequests(User $user): Collection
    {
        return $this->kpRepo->getAvailableRequestsForKP($user->id, $user->city_neighborhood);
    }

    /**
     * Get completed requests for the KP
     * Requests that have been completed and approved/finalized
     * Ordered by completion date descending
     */
    public function getCompletedRequests(User $user): Collection
    {
        return $this->kpRepo->getCompletedRequestsForKP($user->id);
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            UserKnowledgeRequest::STATUS_PENDING => 'Pending',
            UserKnowledgeRequest::STATUS_IN_PROGRESS => 'In Progress',
            UserKnowledgeRequest::STATUS_AWAITING_REVIEW => 'Awaiting Review',
            UserKnowledgeRequest::STATUS_COMPLETED => 'Completed',
            UserKnowledgeRequest::STATUS_APPROVED => 'Approved',
            UserKnowledgeRequest::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get final status label for completed requests
     */
    public function getFinalStatusLabel(string $status, bool $isPaid): string
    {
        if ($isPaid) {
            return 'Paid';
        }

        return match ($status) {
            UserKnowledgeRequest::STATUS_COMPLETED => 'Completed',
            UserKnowledgeRequest::STATUS_APPROVED => 'Approved',
            default => 'Completed',
        };
    }
}
