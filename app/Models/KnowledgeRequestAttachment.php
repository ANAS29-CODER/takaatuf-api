<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeRequestAttachment extends Model
{
    //

      protected $fillable = [
        'knowledge_request_id',
        'type',
        'path',
        'size',
    ];

      public function knowledgeRequest()
    {
        return $this->belongsTo(KnowledgeRequest::class);
    }
}
