<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use KnowledgeRequestMediaRepository;

class KnowledgeRequest extends Model
{

     const STATUS_AVAILABLE = 'available';
     const STATUS_ACTIVE = 'active';
     const STATUS_COMPLETED = 'completed';

       public static function getStatuses()
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
        ];
    }

    const CATEGORY_SURVEY = 'Survey';
    const CATEGORY_ESSAY = 'Essay';
    const CATEGORY_PHOTO = 'Photo';
    const CATEGORY_VIDEO = 'Video';
    const CATEGORY_ERRAND = 'Errand';

    public static function getCategories()
    {
        return [
            self::CATEGORY_SURVEY,
            self::CATEGORY_ESSAY,
            self::CATEGORY_PHOTO,
            self::CATEGORY_VIDEO,
            self::CATEGORY_ERRAND,
        ];
    }

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
        'progress',
        'due_date',
        'created_by',
        'updated_by'

    ];

     public function media()
    {
        return $this->hasMany(KnowledgeRequestMedia::class);
    }
    public function users()
{
    return $this->belongsToMany(User::class, 'user_knowledge_request');
}


}
