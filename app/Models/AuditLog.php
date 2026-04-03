<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    // Action types for admin operations
    const ACTION_REQUEST_APPROVED = 'request_approved';
    const ACTION_REQUEST_REJECTED = 'request_rejected';
    const ACTION_KP_APPROVED = 'kp_approved';
    const ACTION_KP_REJECTED = 'kp_rejected';
    const ACTION_BUDGET_UPDATED = 'budget_updated';
    const ACTION_PAYOUT_APPROVED = 'payout_approved';
    const ACTION_PAYOUT_REJECTED = 'payout_rejected';
    const ACTION_PAYOUT_COMPLETED = 'payout_completed';
    const ACTION_PAYOUT_FAILED = 'payout_failed';
    const ACTION_SUBMISSION_APPROVED = 'submission_approved';
    const ACTION_SUBMISSION_REJECTED = 'submission_rejected';

    // Entity types
    const ENTITY_KNOWLEDGE_REQUEST = 'knowledge_request';
    const ENTITY_PAYOUT = 'payout';
    const ENTITY_USER_KNOWLEDGE_REQUEST = 'user_knowledge_request';
    const ENTITY_WORK_SUBMISSION = 'work_submission';
    const ENTITY_BUDGET_HISTORY = 'budget_history';

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'location_category',
        'location',
        'user_confirmation',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related entity
     */
    public function entity()
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        $modelClass = match ($this->model_type) {
            self::ENTITY_KNOWLEDGE_REQUEST => KnowledgeRequest::class,
            self::ENTITY_PAYOUT => Payout::class,
            self::ENTITY_USER_KNOWLEDGE_REQUEST => UserKnowledgeRequest::class,
            self::ENTITY_WORK_SUBMISSION => WorkSubmission::class,
            self::ENTITY_BUDGET_HISTORY => BudgetHistory::class,
            default => null,
        };

        if (!$modelClass) {
            return null;
        }

        return $modelClass::find($this->model_id);
    }

    /**
     * Get action label for display
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_REQUEST_APPROVED => 'Request Approved',
            self::ACTION_REQUEST_REJECTED => 'Request Rejected',
            self::ACTION_KP_APPROVED => 'KP Application Approved',
            self::ACTION_KP_REJECTED => 'KP Application Rejected',
            self::ACTION_BUDGET_UPDATED => 'Budget Updated',
            self::ACTION_PAYOUT_APPROVED => 'Payout Approved',
            self::ACTION_PAYOUT_REJECTED => 'Payout Rejected',
            self::ACTION_PAYOUT_COMPLETED => 'Payout Completed',
            self::ACTION_PAYOUT_FAILED => 'Payout Failed',
            self::ACTION_SUBMISSION_APPROVED => 'Submission Approved',
            self::ACTION_SUBMISSION_REJECTED => 'Submission Rejected',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
