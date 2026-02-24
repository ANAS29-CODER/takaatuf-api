<?php
// app/Repositories/SocialAccountRepository.php
namespace App\Repositories;

use App\Models\KnowledgeRequest;

  class KnowledgeRequestRepository {


    public function create(array $data)
    {
        return KnowledgeRequest::create($data);
    }

// public function getActiveRequests($user)
// {
//     return KnowledgeRequest::where('user_id', $user->id)
//         ->activeForKr()
//         ->latest()
//         ->get();
// }

public function getActiveRequests($user)
{
    return KnowledgeRequest::where('user_id', $user->id)
        ->whereIn('status', [
            KnowledgeRequest::STATUS_AVAILABLE,
            KnowledgeRequest::STATUS_ACTIVE
        ])
        ->where('progress', '<', 100)
        ->latest()
        ->get();
}


   public function getCompletedRequests($user)
{
    return KnowledgeRequest::where('user_id', $user->id)
        ->where('status', KnowledgeRequest::STATUS_COMPLETED)
        ->where('progress', 100)
        ->latest()
        ->get();
}


}

