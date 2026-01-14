<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use KnowledgeRequestMediaRepository;

class KnowledgeRequest extends Model
{
    //

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
    ];

     public function media()
    {
        return $this->hasMany(KnowledgeRequestMediaRepository::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
