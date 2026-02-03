<?php

namespace App\Repositories;

use App\Models\KnowledgeRequest;
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
        return KnowledgeRequest::select('knowledge_requests.*')
            ->join('user_knowledge_request', 'knowledge_requests.id', '=', 'user_knowledge_request.knowledge_request_id')
            ->where('user_knowledge_request.user_id', $userId)
            ->whereIn('user_knowledge_request.status', UserKnowledgeRequest::getActiveStatuses())
            ->orderBy('user_knowledge_request.updated_at', 'desc')
            ->with(['media'])
            ->get()
            ->map(function ($request) use ($userId) {
                $pivot = DB::table('user_knowledge_request')
                    ->where('user_id', $userId)
                    ->where('knowledge_request_id', $request->id)
                    ->first();

                $request->kp_status = $pivot->status ?? null;
                $request->kp_progress = $pivot->progress ?? 0;
                $request->kp_payout_amount = $pivot->payout_amount ?? $request->pay_per_kp;

                return $request;
            });
    }

    /**
     * Get available requests for a Knowledge Provider
     * These are requests the KP is eligible to accept and not currently assigned to them
     * Ordered by most recently created first
     */
    public function getAvailableRequestsForKP(int $userId, ?string $neighborhood = null): Collection
    {
        $assignedRequestIds = DB::table('user_knowledge_request')
            ->where('user_id', $userId)
            ->pluck('knowledge_request_id')
            ->toArray();

        $query = KnowledgeRequest::where('status', KnowledgeRequest::STATUS_AVAILABLE)
            ->whereNotIn('id', $assignedRequestIds);

        // Filter by neighborhood if provided (optional - depends on business logic)
        // if ($neighborhood) {
        //     $query->where('neighborhood', $neighborhood);
        // }

        return $query->orderBy('created_at', 'desc')
            ->with(['media'])
            ->get()
            ->map(function ($request) {
                // Calculate KPs still needed
                $assignedCount = DB::table('user_knowledge_request')
                    ->where('knowledge_request_id', $request->id)
                    ->whereIn('status', UserKnowledgeRequest::getActiveStatuses())
                    ->count();

                $request->kps_still_needed = max(0, $request->number_of_kps - $assignedCount);

                return $request;
            })
            ->filter(function ($request) {
                // Only show requests that still need KPs
                return $request->kps_still_needed > 0;
            })
            ->values();
    }

    /**
     * Get completed requests for a Knowledge Provider
     * These are requests that have been completed and approved/finalized
     * Ordered by completion date descending
     */
    public function getCompletedRequestsForKP(int $userId): Collection
    {
        return KnowledgeRequest::select('knowledge_requests.*')
            ->join('user_knowledge_request', 'knowledge_requests.id', '=', 'user_knowledge_request.knowledge_request_id')
            ->where('user_knowledge_request.user_id', $userId)
            ->whereIn('user_knowledge_request.status', UserKnowledgeRequest::getCompletedStatuses())
            ->orderBy('user_knowledge_request.completed_at', 'desc')
            ->orderBy('user_knowledge_request.updated_at', 'desc')
            ->get()
            ->map(function ($request) use ($userId) {
                $pivot = DB::table('user_knowledge_request')
                    ->where('user_id', $userId)
                    ->where('knowledge_request_id', $request->id)
                    ->first();

                $request->kp_status = $pivot->status ?? null;
                $request->kp_payout_amount = $pivot->payout_amount ?? $request->pay_per_kp;
                $request->completed_at = $pivot->completed_at ?? $pivot->updated_at;

                return $request;
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

        // If progress is 100, mark as awaiting review
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
    public function getAssignment(int $userId, int $requestId): ?object
    {
        return DB::table('user_knowledge_request')
            ->where('user_id', $userId)
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

        if ($status === UserKnowledgeRequest::STATUS_COMPLETED || $status === UserKnowledgeRequest::STATUS_APPROVED) {
            $updateData['completed_at'] = now();
            $updateData['progress'] = 100;
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
}
