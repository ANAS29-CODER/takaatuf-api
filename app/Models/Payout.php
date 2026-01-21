<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public CONST PAYOUT_STATUS=[
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED
    ];

    protected $fillable = [
        'user_id',
        'amount',
        'wallet_id',
        'status',
        'payout_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payout_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet():BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the last 4 characters of the wallet address
     */
    public function getWalletAddressLastFourAttribute(): string
    {
        return substr($this->wallet_id, -4);
    }
}
