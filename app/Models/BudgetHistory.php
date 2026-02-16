<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetHistory extends Model
{
    use HasFactory;

    const TYPE_INCREASE = 'increase';
    const TYPE_DECREASE = 'decrease';
    const TYPE_PARTIAL_REFUND = 'partial_refund';
    const TYPE_FULL_REFUND = 'full_refund';

    public static function getChangeTypes(): array
    {
        return [
            self::TYPE_INCREASE,
            self::TYPE_DECREASE,
            self::TYPE_PARTIAL_REFUND,
            self::TYPE_FULL_REFUND,
        ];
    }

    protected $fillable = [
        'knowledge_request_id',
        'admin_id',
        'previous_budget',
        'new_budget',
        'previous_pay_per_kp',
        'new_pay_per_kp',
        'change_type',
        'reason',
    ];

    protected $casts = [
        'previous_budget' => 'decimal:2',
        'new_budget' => 'decimal:2',
        'previous_pay_per_kp' => 'decimal:2',
        'new_pay_per_kp' => 'decimal:2',
    ];

    /**
     * Get the knowledge request
     */
    public function knowledgeRequest(): BelongsTo
    {
        return $this->belongsTo(KnowledgeRequest::class);
    }

    /**
     * Get the admin who made the change
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the budget difference
     */
    public function getBudgetDifferenceAttribute(): float
    {
        return (float) $this->new_budget - (float) $this->previous_budget;
    }

    /**
     * Get the change type label
     */
    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            self::TYPE_INCREASE => 'Budget Increase',
            self::TYPE_DECREASE => 'Budget Decrease',
            self::TYPE_PARTIAL_REFUND => 'Partial Refund',
            self::TYPE_FULL_REFUND => 'Full Refund',
            default => ucfirst(str_replace('_', ' ', $this->change_type)),
        };
    }
}
