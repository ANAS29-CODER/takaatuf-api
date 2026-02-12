<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserKnowledgeRequest extends Pivot
{
    protected $table = 'user_knowledge_request';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_AWAITING_REVIEW = 'awaiting_review';
    const STATUS_COMPLETED = 'completed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_AWAITING_REVIEW,
            self::STATUS_COMPLETED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    public static function getActiveStatuses(): array
    {
        return [
            // self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_AWAITING_REVIEW,
        ];
    }

    public static function getCompletedStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_APPROVED,
        ];
    }

    protected $fillable = [
        'user_id',
        'knowledge_request_id',
        'status',
        'progress',
        'payout_amount',
        'completed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'payout_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeRequest()
    {
        return $this->belongsTo(KnowledgeRequest::class);
    }
}
