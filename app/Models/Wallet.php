<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    const TYPE_ETHEREUM = 'ethereum';
    const TYPE_SOLANA = 'solana';
    const TYPE_BITCOIN = 'bitcoin';

    public const WALLET_TYPES = [
        self::TYPE_ETHEREUM,
        self::TYPE_SOLANA,
        self::TYPE_BITCOIN,
    ];

    protected $fillable = [
        'user_id',
        'wallet_type',
        'wallet_address',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the last 4 characters of the wallet address
     */
    public function getAddressLastFourAttribute(): string
    {
        return substr($this->wallet_address, -4);
    }


}
