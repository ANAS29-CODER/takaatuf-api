<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an admin action
     */
    public function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
        ?string $location = null
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'location' => $location ?? 'Gaza strip',
        ]);
    }

    /**
     * Log knowledge request approval
     */
    public function logRequestApproval(int $requestId, array $oldValues, array $newValues, ?Request $request = null, ?string $location = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_REQUEST_APPROVED,
            AuditLog::ENTITY_KNOWLEDGE_REQUEST,
            $requestId,
            $oldValues,
            $newValues,
            $request,
            $location
        );
    }

    /**
     * Log knowledge request rejection
     */
    public function logRequestRejection(int $requestId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_REQUEST_REJECTED,
            AuditLog::ENTITY_KNOWLEDGE_REQUEST,
            $requestId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log KP application approval
     */
    public function logKPApproval(int $assignmentId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_KP_APPROVED,
            AuditLog::ENTITY_USER_KNOWLEDGE_REQUEST,
            $assignmentId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log KP application rejection
     */
    public function logKPRejection(int $assignmentId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_KP_REJECTED,
            AuditLog::ENTITY_USER_KNOWLEDGE_REQUEST,
            $assignmentId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log budget update
     */
    public function logBudgetUpdate(int $requestId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_BUDGET_UPDATED,
            AuditLog::ENTITY_KNOWLEDGE_REQUEST,
            $requestId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log payout completion
     */
    public function logPayoutCompleted(int $payoutId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PAYOUT_COMPLETED,
            AuditLog::ENTITY_PAYOUT,
            $payoutId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log payout failure
     */
    public function logPayoutFailed(int $payoutId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PAYOUT_FAILED,
            AuditLog::ENTITY_PAYOUT,
            $payoutId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log payout approval
     */
    public function logPayoutApproval(int $payoutId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PAYOUT_APPROVED,
            AuditLog::ENTITY_PAYOUT,
            $payoutId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log payout rejection
     */
    public function logPayoutRejection(int $payoutId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PAYOUT_REJECTED,
            AuditLog::ENTITY_PAYOUT,
            $payoutId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log submission approval
     */
    public function logSubmissionApproval(int $submissionId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_SUBMISSION_APPROVED,
            AuditLog::ENTITY_WORK_SUBMISSION,
            $submissionId,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log submission rejection
     */
    public function logSubmissionRejection(int $submissionId, array $oldValues, array $newValues, ?Request $request = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_SUBMISSION_REJECTED,
            AuditLog::ENTITY_WORK_SUBMISSION,
            $submissionId,
            $oldValues,
            $newValues,
            $request
        );
    }
}
