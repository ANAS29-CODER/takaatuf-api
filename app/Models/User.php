<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;


    const KNOWLEDGE_PROVIDER = 'Knowledge Provider';
    const KNOWLEDGE_REQUESTER = 'Knowledge Requester';
    const ADMIN = 'Admin';

    public const USER_ROLE = [
        self::KNOWLEDGE_PROVIDER,
        self::KNOWLEDGE_REQUESTER,
        self::ADMIN,
    ];
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'oauth_provider',
        'oauth_provider_id',
        'profile_completed',
        'city_neighborhood',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_completed' => 'boolean',
        ];
    }

    // app/Models/User.php
    public function socialAccounts()
    {
        return $this->hasMany(\App\Models\SocialAccount::class);
    }

        public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function earnings()
    {
        return $this->hasMany(Earning::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function primaryWallet()
    {
        return $this->hasOne(Wallet::class)->where('is_primary', true);
    }
    /**
     * Get all knowledge requests assigned to this KP
     */
    public function knowledgeRequests()
    {
        return $this->belongsToMany(KnowledgeRequest::class, 'user_knowledge_request')
            ->using(UserKnowledgeRequest::class)
            ->withPivot(['status', 'progress', 'payout_amount', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Get active knowledge requests for this KP
     */
    public function activeKnowledgeRequests()
    {
        return $this->knowledgeRequests()
            ->whereIn('user_knowledge_request.status', UserKnowledgeRequest::getActiveStatuses());
    }

    /**
     * Get completed knowledge requests for this KP
     */
    public function completedKnowledgeRequests()
    {
        return $this->knowledgeRequests()
            ->whereIn('user_knowledge_request.status', UserKnowledgeRequest::getCompletedStatuses());
    }


    public function paypalAccount()
    {
        return $this->hasOne(PaypalAccount::class);
    }
}
