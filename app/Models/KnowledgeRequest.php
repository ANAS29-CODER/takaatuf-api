<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeRequest extends Model
{
    use HasFactory;

    // Moderation statuses
    const STATUS_PENDING_MODERATION = 'pending_moderation';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Workflow statuses
    const STATUS_AVAILABLE = 'available';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';

    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING_MODERATION,
            self::STATUS_APPROVED,
            self::STATUS_AVAILABLE,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
        ];
    }

    public static function getModerationStatuses()
    {
        return [
            self::STATUS_PENDING_MODERATION,
        ];
    }

    public static function getApprovedStatuses()
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_AVAILABLE,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
        ];
    }

    const CATEGORY_SURVEY = 'Survey';
    const CATEGORY_ESSAY = 'Essay';
    const CATEGORY_PHOTO = 'Photo';
    const CATEGORY_VIDEO = 'Video';
    const CATEGORY_ERRAND = 'Errand';

    public static function getCategories()
    {
        return [
            self::CATEGORY_SURVEY,
            self::CATEGORY_ESSAY,
            self::CATEGORY_PHOTO,
            self::CATEGORY_VIDEO,
            self::CATEGORY_ERRAND,
        ];
    }

    protected $fillable = [
        'user_id',
        'category',
        'details',
        'pay_per_kp',
        'number_of_kps',
        'review_fee',
        'total_budget',
        'neighborhood',
        'status',
        'progress',
        'due_date',
        'created_by',
        'updated_by',
        'moderated_by',
        'moderated_at',
        'rejection_reason',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
        'due_date' => 'datetime',
        'pay_per_kp' => 'decimal:2',
        'total_budget' => 'decimal:2',
        'review_fee' => 'decimal:2',
    ];

     public function media()
    {
        return $this->hasMany(KnowledgeRequestMedia::class);
    }
    /**
     * Get the Knowledge Requester who created this request
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all Knowledge Providers assigned to this request
     */
    public function knowledgeProviders()
    {
        return $this->belongsToMany(User::class, 'user_knowledge_request')
            ->using(UserKnowledgeRequest::class)
            ->withPivot(['status', 'progress', 'payout_amount', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Alias for backwards compatibility
     */
    public function users()
    {
        return $this->knowledgeProviders();
    }

    /**
     * Get count of KPs still needed for this request
     */
    public function getKpsStillNeededAttribute(): int
    {
        $assignedCount = $this->knowledgeProviders()
            ->whereIn('user_knowledge_request.status', UserKnowledgeRequest::getActiveStatuses())
            ->count();

        return max(0, $this->number_of_kps - $assignedCount);
    }

    /**
     * Get all work submissions for this request
     */
    public function workSubmissions()
    {
        return $this->hasMany(WorkSubmission::class);
    }

    /**
     * Get the user (alias for creator)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who moderated this request
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Get budget history for this request
     */
    public function budgetHistories()
    {
        return $this->hasMany(BudgetHistory::class);
    }

    /**
     * Check if request is pending moderation
     */
    public function isPendingModeration(): bool
    {
        return $this->status === self::STATUS_PENDING_MODERATION;
    }

    /**
     * Check if request is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->status, self::getApprovedStatuses());
    }

    /**
     * Check if request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
