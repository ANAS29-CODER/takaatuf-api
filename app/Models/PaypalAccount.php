<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaypalAccount extends Model
{
    const STATUS_NOT_CONNECTED = 'not_connected';
    const STATUS_CONNECTED = 'connected';
    const STATUS_FAILED = 'failed';

    const PAYPAL_ACCOUNT_STATUSES = [
        self::STATUS_NOT_CONNECTED,
        self::STATUS_CONNECTED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'user_id',
        'paypal_email',
        'paypal_account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }
        return $this->token_expires_at->isPast();
    }
}
