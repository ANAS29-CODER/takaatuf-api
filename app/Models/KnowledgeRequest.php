<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeRequest extends Model
{
    //

     protected $fillable = [
        'user_id',
        'category',
        'details',
        'pay_per_kp',
        'number_of_providers',
        'total_budget',
        'neighborhood',
        'status'
    ];

    public function attachments()
    {
        return $this->hasMany(KnowledgeRequestAttachment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
