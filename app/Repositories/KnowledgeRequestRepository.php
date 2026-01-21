<?php
// app/Repositories/SocialAccountRepository.php
namespace App\Repositories;

use App\Models\KnowledgeRequest;

  class KnowledgeRequestRepository {


    public function create(array $data)
    {
        return KnowledgeRequest::create($data);
    }

  public function getActiveRequests($user)
    {
        return KnowledgeRequest::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCompletedRequests($user)
    {
        return KnowledgeRequest::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // public function getAvailableRequests(){

    //     return KnowledgeRequest::where('status')

    // }

}

