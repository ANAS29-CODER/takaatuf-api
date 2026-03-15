<?php

namespace App\Repositories;

use App\Models\KnowledgeRequest;
use App\Models\User;
use App\Models\UserKnowledgeRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KnowledgeProviderRepository
{
    /**
     * Get active requests for a Knowledge Provider
     * These are requests assigned to the KP or accepted by them and in progress
     * Ordered by most recently updated first
     */
    public function getActiveRequestsForKP(int $userId): Collection
    {
        return User::findOrFail($userId)
            ->knowledgeRequests()
            ->wherePivotIn('status', UserKnowledgeRequest::getActiveStatuses())
            ->orderByPivot('updated_at', 'desc')
            ->with('media')
            ->get()
            ->each(function ($request) {
                $request->kp_status = $request->pivot->status;
                $request->kp_progress = $request->pivot->progress ?? 0;
                $request->kp_payout_amount = $request->pivot->payout_amount ?? $request->pay_per_kp;
            });
    }

    /**
     * Get available requests for a Knowledge Provider
     * These are requests the KP is eligible to accept and not currently assigned to them
     * Ordered by most recently created first
     */
    public function getAvailableRequestsForKP(int $userId, ?string $neighborhood = null): Collection
    {
        return KnowledgeRequest::query()
            ->where('status', KnowledgeRequest::STATUS_AVAILABLE)
            ->whereDoesntHave('users', fn ($q) => $q->where('users.id', $userId))
            //   ->when($neighborhood, fn($q) => $q->where('neighborhood', $neighborhood))
            ->where(function ($q) {
                $q->whereRaw('number_of_kps > (
                SELECT COUNT(*)
                FROM user_knowledge_request
                WHERE knowledge_request_id = knowledge_requests.id
                AND status IN ("in_progress","awaiting_review")
            )');
            })
            ->latest()
            ->with('media')
            ->get();
    }

    /**
     * Get completed requests for a Knowledge Provider
     * These are requests that have been completed and approved/finalized
     * Ordered by completion date descending
     */
    public function getCompletedRequestsForKP(int $userId): Collection
    {
        return KnowledgeRequest::query()
            ->select([
                'knowledge_requests.*',
                'ukr.status as kp_status',
                'ukr.payout_amount as kp_payout_amount',
                DB::raw('COALESCE(ukr.completed_at, ukr.updated_at) as kp_completed_at'),
            ])
            ->join('user_knowledge_request as ukr', function ($join) use ($userId) {
                $join->on('knowledge_requests.id', '=', 'ukr.knowledge_request_id')
                    ->where('ukr.user_id', $userId)
                    ->whereIn('ukr.status', UserKnowledgeRequest::getCompletedStatuses());
            })
            ->orderByDesc('ukr.completed_at')
            ->orderByDesc('ukr.updated_at')
            ->with('media')
            ->get();
    }

    public function getPendingRequestsForKP(int $userId): Collection
    {
        return User::findOrFail($userId)
            ->knowledgeRequests()
            ->wherePivot('status', UserKnowledgeRequest::STATUS_PENDING)
            ->orderByPivot('updated_at', 'desc')
            ->with('media')
            ->get()
            ->each(function ($request) {
                $request->kp_status = $request->pivot->status;
                $request->kp_progress = $request->pivot->progress;
                $request->kp_payout_amount = $request->pivot->payout_amount ?? $request->pay_per_kp;
            });
    }

    public function getSubmittedRequestsForKP(int $userId): Collection
    {
        return User::findOrFail($userId)
            ->knowledgeRequests()
            ->wherePivot('status', UserKnowledgeRequest::STATUS_AWAITING_REVIEW)
            ->orderByPivot('updated_at', 'desc')
            ->with('media')
            ->get()
            ->each(function ($request) {
                $request->kp_status = $request->pivot->status;
                $request->kp_progress = $request->pivot->progress;
                $request->kp_payout_amount = $request->pivot->payout_amount ?? $request->pay_per_kp;
            });
    }

    public function getRejectedRequestsForKP(int $userId): Collection
    {
        return User::findOrFail($userId)
            ->knowledgeRequests()
            ->wherePivot('status', UserKnowledgeRequest::STATUS_REJECTED)
            ->orderByPivot('updated_at', 'desc')
            ->with('media')
            ->get()
            ->each(function ($request) {
                $request->kp_status = $request->pivot->status;
                $request->kp_progress = $request->pivot->progress;
                $request->kp_payout_amount = $request->pivot->payout_amount ?? $request->pay_per_kp;
            });
    }

    /**
     * Apply to a knowledge request
     */
    public function applyToRequest(int $userId, int $requestId, float $payoutAmount): bool
    {
        $exists = DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->exists();

        if ($exists) {
            return false;
        }

        DB::table('user_knowledge_request')->insert([
            'user_id' => $userId,
            'knowledge_request_id' => $requestId,
            'status' => UserKnowledgeRequest::STATUS_PENDING,
            'progress' => 0,
            'payout_amount' => $payoutAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Update progress for a KP's assignment
     */
    public function updateProgress(int $userId, int $requestId, int $progress, ?string $status = null): bool
    {
        $updateData = [
            'progress' => min(100, max(0, $progress)),
            'updated_at' => now(),
        ];

        if ($status) {
            $updateData['status'] = $status;
        }

        if ($progress >= 100 && !$status) {
            $updateData['status'] = UserKnowledgeRequest::STATUS_AWAITING_REVIEW;
        }

        return DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->update($updateData) > 0;
    }

    /**
     * Mark assignment as completed
     */
    public function markCompleted(int $userId, int $requestId): bool
    {
        return DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->update([
                'status' => UserKnowledgeRequest::STATUS_COMPLETED,
                'progress' => 100,
                'completed_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Check if KP is assigned to a request
     */
    public function isAssignedToRequest(int $userId, int $requestId): bool
    {
        return DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->exists();
    }

    /**
     * Get assignment details
     */
    public function getAssignment(int $userId, int $requestId): ?UserKnowledgeRequest
    {
        return UserKnowledgeRequest::where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->first();
    }

    /**
     * Get task page details for a KP
     * Returns full request information with KP-specific assignment data
     */
    public function getTaskPageDetails(int $userId, int $requestId): ?KnowledgeRequest
    {
        $assignment = $this->getAssignment($userId, $requestId);

        if (!$assignment) {
            return null;
        }

        $request = KnowledgeRequest::with(['media', 'user'])
            ->find($requestId);


        if (!$request) {
            return null;
        }

        // Attach KP-specific data
        $request->kp_status = $assignment->status;
        $request->kp_progress = $assignment->progress ?? 0;
        $request->kp_payout_amount = $assignment->payout_amount ?? $request->pay_per_kp;
        $request->kp_completed_at = $assignment->completed_at;

        // Get total KPs assigned to this request
        $request->total_kps_assigned = DB::table('user_knowledge_request')
            ->where('knowledge_request_id', $requestId)
            ->whereIn('status', array_merge(
                UserKnowledgeRequest::getActiveStatuses(),
                UserKnowledgeRequest::getCompletedStatuses()
            ))
            ->count();

        return $request;
    }

    /**
     * Update KP assignment status
     */
    public function updateAssignmentStatus(int $userId, int $requestId, string $status): bool
    {
        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if (in_array($status, [
            UserKnowledgeRequest::STATUS_COMPLETED,
            UserKnowledgeRequest::STATUS_APPROVED,
        ])) {
            $updateData['progress'] = UserKnowledgeRequest::PROGRESS_REVIEWED;
            $updateData['completed_at'] = now();
        }

        return DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->update($updateData) > 0;
    }

    /**
     * Check if assignment can be edited (not approved yet)
     */
    public function canEditAssignment(int $userId, int $requestId): bool
    {
        $assignment = $this->getAssignment($userId, $requestId);

        if (!$assignment) {
            return false;
        }

        return !in_array($assignment->status, [
            UserKnowledgeRequest::STATUS_APPROVED,
        ]);
    }

    /**
     * Get assignment status label for display
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            UserKnowledgeRequest::STATUS_PENDING => 'Pending',
            UserKnowledgeRequest::STATUS_IN_PROGRESS => 'In Progress',
            UserKnowledgeRequest::STATUS_AWAITING_REVIEW => 'Submitted',
            UserKnowledgeRequest::STATUS_COMPLETED => 'Completed',
            UserKnowledgeRequest::STATUS_APPROVED => 'Approved',
            UserKnowledgeRequest::STATUS_REJECTED => 'Rejected',
        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }


    // Calculate the progress for KR request
    public function recalculateRequestProgress(int $requestId): void
    {
        $request = KnowledgeRequest::find($requestId);
        if (!$request) {
            return;
        }

        $sumProgress = UserKnowledgeRequest::query()
            ->where('knowledge_request_id', $requestId)
            ->where('progress', '>', 0)
            ->sum('progress');

        $totalSlots = $request->number_of_kps;

        $requestProgress = $totalSlots > 0
            ? (int) round($sumProgress / $totalSlots)
            : 0;

        $requestProgress = min(100, $requestProgress);

        $request->update([
            'progress' => $requestProgress,
            'status'   => $requestProgress >= 100
                ? KnowledgeRequest::STATUS_COMPLETED
                : $request->status,
        ]);
    }
}
