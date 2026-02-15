<?php

namespace App\Services;

use App\Models\BudgetHistory;
use App\Models\KnowledgeRequest;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserKnowledgeRequest;
use App\Models\WorkSubmission;
use App\Repositories\AdminRepository;
use App\Repositories\EarningRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    protected AdminRepository $adminRepo;
    protected AuditLogService $auditLogService;
    protected TaskPageService $taskPageService;
    protected EarningRepository $earningRepo;

    public function __construct(
        AdminRepository $adminRepo,
        AuditLogService $auditLogService,
        TaskPageService $taskPageService,
        EarningRepository $earningRepo
    ) {
        $this->adminRepo = $adminRepo;
        $this->auditLogService = $auditLogService;
        $this->taskPageService = $taskPageService;
        $this->earningRepo = $earningRepo;
    }

    /**
     * Get dashboard overview statistics
     */
    public function getDashboardStats(): array
    {
        return $this->adminRepo->getDashboardStats();
    }

    /**
     * Get pending knowledge requests for moderation
     */
    public function getPendingRequests(int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getPendingRequests($perPage);
    }

    /**
     * Get all knowledge requests with filters
     */
    public function getRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getRequests($filters, $perPage);
    }

    /**
     * Get knowledge request details
     */
    public function getRequestDetails(int $requestId): ?KnowledgeRequest
    {
        return $this->adminRepo->getRequestById($requestId);
    }

    /**
     * Approve a knowledge request
     */
    public function approveRequest(int $requestId, User $admin, ?Request $httpRequest = null): array
    {
        $request = $this->adminRepo->getRequestById($requestId);

        $location= $request->neighborhood;


        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        if (!$request->isPendingModeration()) {
            return ['success' => false, 'message' => 'Request is not pending moderation.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => $request->status];
            $request = $this->adminRepo->updateRequestStatus($requestId, KnowledgeRequest::STATUS_APPROVED, $admin->id);
            $newValues = ['status' => $request->status];

            $this->auditLogService->logRequestApproval($requestId, $oldValues, $newValues, $httpRequest,$location);

            DB::commit();

            return ['success' => true, 'message' => 'Request approved successfully.', 'request' => $request];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to approve request: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a knowledge request
     */
    public function rejectRequest(int $requestId, User $admin, ?string $reason = null, ?Request $httpRequest = null): array
    {
        $request = $this->adminRepo->getRequestById($requestId);

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        if (!$request->isPendingModeration()) {
            return ['success' => false, 'message' => 'Request is not pending moderation.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => $request->status];
            $request = $this->adminRepo->updateRequestStatus($requestId, KnowledgeRequest::STATUS_REJECTED, $admin->id, $reason);
            $newValues = ['status' => $request->status, 'rejection_reason' => $reason];

            $this->auditLogService->logRequestRejection($requestId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Request rejected successfully.', 'request' => $request];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to reject request: ' . $e->getMessage()];
        }
    }

    /**
     * Get KP applications for a request
     */
    public function getKPApplications(int $requestId): Collection
    {
        return $this->adminRepo->getKPApplications($requestId);
    }

    /**
     * Get all pending KP applications
     */
    public function getPendingKPApplications(int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getPendingKPApplications($perPage);
    }

    /**
     * Approve a KP application
     */
    public function approveKPApplication(int $userId, int $requestId, User $admin, ?Request $httpRequest = null): array
    {
        $request = $this->adminRepo->getRequestById($requestId);

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        // Check if request is available or active
        if (!in_array($request->status, [KnowledgeRequest::STATUS_AVAILABLE, KnowledgeRequest::STATUS_ACTIVE])) {
            return ['success' => false, 'message' => 'Request is not available for KP assignment.'];
        }

        // Check if KPs still needed
        if ($request->kps_still_needed <= 0) {
            return ['success' => false, 'message' => 'No more KPs needed for this request.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => UserKnowledgeRequest::STATUS_PENDING];
            $assignment = $this->adminRepo->updateKPApplicationStatus($userId, $requestId, UserKnowledgeRequest::STATUS_IN_PROGRESS);
            $newValues = ['status' => $assignment->status];

            // Set payout amount
            $assignment->payout_amount = $request->pay_per_kp;
            $assignment->save();

            // Update request status to active if it was available
            if ($request->status === KnowledgeRequest::STATUS_AVAILABLE) {
                $request->status = KnowledgeRequest::STATUS_ACTIVE;
                $request->save();
            }


            $this->auditLogService->logKPApproval($assignment->id, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'KP application approved successfully.', 'assignment' => $assignment];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to approve KP application: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a KP application
     */
    public function rejectKPApplication(int $userId, int $requestId, User $admin, ?Request $httpRequest = null): array
    {
        try {
            DB::beginTransaction();

            $oldValues = ['status' => UserKnowledgeRequest::STATUS_PENDING];
            $assignment = $this->adminRepo->updateKPApplicationStatus($userId, $requestId, UserKnowledgeRequest::STATUS_REJECTED);
            $newValues = ['status' => $assignment->status];

            $this->auditLogService->logKPRejection($assignment->id, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'KP application rejected.', 'assignment' => $assignment];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to reject KP application: ' . $e->getMessage()];
        }
    }

    /**
     * Get pending payouts
     */
    public function getPendingPayouts(int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getPendingPayouts($perPage);
    }

    /**
     * Get all payouts with filters
     */
    public function getPayouts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getPayouts($filters, $perPage);
    }

    /**
     * Get payout details
     */
    public function getPayoutDetails(int $payoutId): ?Payout
    {
        return $this->adminRepo->getPayoutById($payoutId);
    }

    /**
     * Complete a payout with transaction ID
     */
    public function completePayout(int $payoutId, string $transactionId, User $admin, ?string $notes = null, ?Request $httpRequest = null): array
    {
        $payout = $this->adminRepo->getPayoutById($payoutId);

        if (!$payout) {
            return ['success' => false, 'message' => 'Payout not found.'];
        }

        if (!$payout->canBeProcessed()) {
            return ['success' => false, 'message' => 'Payout cannot be processed in its current state.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = [
                'status' => $payout->status,
                'transaction_id' => $payout->transaction_id,
            ];

            $payout = $this->adminRepo->completePayout($payoutId, $transactionId, $admin->id, $notes);

            $newValues = [
                'status' => $payout->status,
                'transaction_id' => $payout->transaction_id,
            ];

            $this->auditLogService->logPayoutCompleted($payoutId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Payout completed successfully.', 'payout' => $payout];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to complete payout: ' . $e->getMessage()];
        }
    }

    /**
     * Fail/Cancel a payout
     */
    public function failPayout(int $payoutId, User $admin, ?string $notes = null, ?Request $httpRequest = null): array
    {
        $payout = $this->adminRepo->getPayoutById($payoutId);

        if (!$payout) {
            return ['success' => false, 'message' => 'Payout not found.'];
        }

        if (!$payout->canBeProcessed()) {
            return ['success' => false, 'message' => 'Payout cannot be cancelled in its current state.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => $payout->status];
            $payout = $this->adminRepo->failPayout($payoutId, $admin->id, $notes);
            $newValues = ['status' => $payout->status, 'admin_notes' => $notes];

            $this->auditLogService->logPayoutFailed($payoutId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Payout cancelled/failed.', 'payout' => $payout];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to cancel payout: ' . $e->getMessage()];
        }
    }

    /**
     * Update budget for a knowledge request
     */
    public function updateBudget(int $requestId, float $newBudget, ?float $newPayPerKp, string $changeType, User $admin, ?string $reason = null, ?Request $httpRequest = null): array
    {
        $request = $this->adminRepo->getRequestById($requestId);

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        if ($newBudget < 0) {
            return ['success' => false, 'message' => 'Budget cannot be negative.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = [
                'total_budget' => $request->total_budget,
                'pay_per_kp' => $request->pay_per_kp,
            ];

            $history = $this->adminRepo->updateBudget($requestId, $newBudget, $newPayPerKp, $admin->id, $changeType, $reason);

            $newValues = [
                'total_budget' => $newBudget,
                'pay_per_kp' => $newPayPerKp ?? $request->pay_per_kp,
            ];

            $this->auditLogService->logBudgetUpdate($requestId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Budget updated successfully.', 'history' => $history];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to update budget: ' . $e->getMessage()];
        }
    }

    /**
     * Get budget history for a request
     */
    public function getBudgetHistory(int $requestId): Collection
    {
        return $this->adminRepo->getBudgetHistory($requestId);
    }

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getAuditLogs($filters, $perPage);
    }

    /**
     * Get pending work submissions
     */
    public function getPendingSubmissions(int $perPage = 15): LengthAwarePaginator
    {
        return $this->adminRepo->getPendingSubmissions($perPage);
    }

    /**
     * Get work submission details
     */
    public function getSubmissionDetails(int $submissionId): ?WorkSubmission
    {
        return $this->adminRepo->getSubmissionById($submissionId);
    }

    /**
     * Approve a work submission
     */
    public function approveSubmission(int $submissionId, User $admin, ?Request $httpRequest = null): array
    {
        $submission = $this->adminRepo->getSubmissionById($submissionId);

        if (!$submission) {
            return ['success' => false, 'message' => 'Submission not found.'];
        }

        if ($submission->status !== WorkSubmission::STATUS_SUBMITTED) {
            return ['success' => false, 'message' => 'Submission is not pending review.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => $submission->status];

            $result = $this->taskPageService->approveSubmission(
                $submission->id,
                $submission->knowledge_request_id,
                $submission->user_id
            );

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            $newValues = ['status' => WorkSubmission::STATUS_APPROVED];

            $this->auditLogService->logSubmissionApproval($submissionId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Submission approved successfully.', 'submission' => $result['submission']];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to approve submission: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a work submission
     */
    public function rejectSubmission(int $submissionId, User $admin, ?string $reason = null, ?Request $httpRequest = null): array
    {
        $submission = $this->adminRepo->getSubmissionById($submissionId);

        if (!$submission) {
            return ['success' => false, 'message' => 'Submission not found.'];
        }

        if ($submission->status !== WorkSubmission::STATUS_SUBMITTED) {
            return ['success' => false, 'message' => 'Submission is not pending review.'];
        }

        try {
            DB::beginTransaction();

            $oldValues = ['status' => $submission->status];

            $result = $this->taskPageService->rejectSubmission(
                $submission->id,
                $submission->knowledge_request_id,
                $submission->user_id,
                $reason
            );

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            $newValues = ['status' => WorkSubmission::STATUS_REJECTED, 'rejection_reason' => $reason];

            $this->auditLogService->logSubmissionRejection($submissionId, $oldValues, $newValues, $httpRequest);

            DB::commit();

            return ['success' => true, 'message' => 'Submission rejected.', 'submission' => $result['submission']];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to reject submission: ' . $e->getMessage()];
        }
    }
}
