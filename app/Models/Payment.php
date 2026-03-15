<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'knowledge_request_id',
        'amount',
        'system_fee',
        'payment_fee',
        'total',
        'paypal_order_id',
        'paypal_capture_id',
        'reference_id',
        'idempotency_key',
        'status',
        'failure_reason',
        'payer_email',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'system_fee' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeRequest(): BelongsTo
    {
        return $this->belongsTo(KnowledgeRequest::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
