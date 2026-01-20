<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeRequestMedia extends Model
{
    //

       protected $fillable = [
        'knowledge_request_id',
        'file_path',
        'type',
    ];

    public function request()
    {
        return $this->belongsTo(KnowledgeRequest::class);
    }
}
