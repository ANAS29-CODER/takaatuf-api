<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\BudgetHistory;
use App\Models\Earning;
use App\Models\KnowledgeRequest;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserKnowledgeRequest;
use App\Models\WorkSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminRepository
{
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        return [
            'pending_requests' => KnowledgeRequest::where('status', KnowledgeRequest::STATUS_PENDING_MODERATION)->count(),
            'approved_requests' => KnowledgeRequest::whereIn('status', KnowledgeRequest::getApprovedStatuses())->count(),
            'rejected_requests' => KnowledgeRequest::where('status', KnowledgeRequest::STATUS_REJECTED)->count(),
            'pending_payouts' => Payout::where('status', Payout::STATUS_PENDING)->count(),
            'completed_payouts' => Payout::where('status', Payout::STATUS_COMPLETED)->count(),
            'total_kps' => User::where('role', User::KNOWLEDGE_PROVIDER)->count(),
            'total_krs' => User::where('role', User::KNOWLEDGE_REQUESTER)->count(),
            'pending_submissions' => WorkSubmission::where('status', WorkSubmission::STATUS_SUBMITTED)->count(),
            'total_earnings' => Earning::sum('amount'),
            'total_payouts_amount' => Payout::where('status', Payout::STATUS_COMPLETED)->sum('amount'),
        ];
    }

    /**
     * Get pending knowledge requests for moderation
     */
    public function getPendingRequests(int $perPage = 15): LengthAwarePaginator
    {
        return KnowledgeRequest::with(['creator', 'media'])
            ->where('status', KnowledgeRequest::STATUS_PENDING_MODERATION)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get all knowledge requests with filters
     */
    public function getRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = KnowledgeRequest::with(['creator', 'media', 'moderator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('details', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('neighborhood', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get knowledge request by ID
     */
    public function getRequestById(int $requestId): ?KnowledgeRequest
    {
        return KnowledgeRequest::with(['creator', 'media', 'moderator', 'knowledgeProviders', 'workSubmissions'])
            ->find($requestId);
    }

    /**
     * Update knowledge request status
     */
    public function updateRequestStatus(int $requestId, string $status, int $adminId, ?string $rejectionReason = null): KnowledgeRequest
    {
        $request = KnowledgeRequest::findOrFail($requestId);

        $request->status = $status;
        $request->moderated_by = $adminId;
        $request->moderated_at = now();

        if ($rejectionReason) {
            $request->rejection_reason = $rejectionReason;
        }

        // If approved, make it available for KPs
        if ($status === KnowledgeRequest::STATUS_APPROVED) {
            $request->status = KnowledgeRequest::STATUS_AVAILABLE;
        }

        $request->save();

        return $request;
    }

    /**
     * Get KP applications for a request
     */
    public function getKPApplications(int $requestId): Collection
    {
        return UserKnowledgeRequest::with(['user', 'knowledgeRequest'])
            ->where('knowledge_request_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get pending KP applications across all requests
     */
    public function getPendingKPApplications(int $perPage = 15): LengthAwarePaginator
    {
        return UserKnowledgeRequest::with(['user', 'knowledgeRequest'])
            ->where('status', UserKnowledgeRequest::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Update KP application status
     */
    public function updateKPApplicationStatus(int $userId, int $requestId, string $status): UserKnowledgeRequest
    {
        $assignment = UserKnowledgeRequest::where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->firstOrFail();

        $assignment->status = $status;
        $assignment->save();

        return $assignment;
    }

    /**
     * Get pending payouts
     */
    public function getPendingPayouts(int $perPage = 15): LengthAwarePaginator
    {
        return Payout::with(['user', 'wallet'])
            ->where('status', Payout::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get all payouts with filters
     */
    public function getPayouts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Payout::with(['user', 'wallet', 'processor']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get payout by ID
     */
    public function getPayoutById(int $payoutId): ?Payout
    {
        return Payout::with(['user', 'wallet', 'processor'])->find($payoutId);
    }

    /**
     * Complete payout with transaction ID
     */
    public function completePayout(int $payoutId, string $transactionId, int $adminId, ?string $notes = null): Payout
    {
        $payout = Payout::findOrFail($payoutId);

        $payout->status = Payout::STATUS_COMPLETED;
        $payout->transaction_id = $transactionId;
        $payout->processed_by = $adminId;
        $payout->processed_at = now();
        $payout->payout_at = now();

        if ($notes) {
            $payout->admin_notes = $notes;
        }

        $payout->save();

        return $payout;
    }

    /**
     * Fail/Cancel payout
     */
    public function failPayout(int $payoutId, int $adminId, ?string $notes = null): Payout
    {
        $payout = Payout::findOrFail($payoutId);

        $payout->status = Payout::STATUS_FAILED;
        $payout->processed_by = $adminId;
        $payout->processed_at = now();

        if ($notes) {
            $payout->admin_notes = $notes;
        }

        $payout->save();

        return $payout;
    }

    /**
     * Update budget for a knowledge request
     */
    public function updateBudget(int $requestId, float $newBudget, ?float $newPayPerKp, int $adminId, string $changeType, ?string $reason = null): BudgetHistory
    {
        $request = KnowledgeRequest::findOrFail($requestId);

        $history = BudgetHistory::create([
            'knowledge_request_id' => $requestId,
            'admin_id' => $adminId,
            'previous_budget' => $request->total_budget,
            'new_budget' => $newBudget,
            'previous_pay_per_kp' => $request->pay_per_kp,
            'new_pay_per_kp' => $newPayPerKp ?? $request->pay_per_kp,
            'change_type' => $changeType,
            'reason' => $reason,
        ]);

        $request->total_budget = $newBudget;
        if ($newPayPerKp !== null) {
            $request->pay_per_kp = $newPayPerKp;
        }
        $request->save();

        return $history;
    }

    /**
     * Get budget history for a request
     */
    public function getBudgetHistory(int $requestId): Collection
    {
        return BudgetHistory::with('admin')
            ->where('knowledge_request_id', $requestId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AuditLog::with('user');

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }

        if (!empty($filters['model_id'])) {
            $query->where('model_id', $filters['model_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get pending work submissions
     */
    public function getPendingSubmissions(int $perPage = 15): LengthAwarePaginator
    {
        return WorkSubmission::with(['user', 'knowledgeRequest', 'media'])
            ->where('status', WorkSubmission::STATUS_SUBMITTED)
            ->orderBy('submitted_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get work submission by ID
     */
    public function getSubmissionById(int $submissionId): ?WorkSubmission
    {
        return WorkSubmission::with(['user', 'knowledgeRequest', 'media'])->find($submissionId);
    }
}
