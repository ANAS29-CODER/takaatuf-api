<?php
// app/Repositories/SocialAccountRepository.php
namespace App\Repositories;

use App\Models\KnowledgeRequest;

  class KnowledgeRequestRepository {


    public function create(array $data)
    {
        return KnowledgeRequest::create($data);
    }

}

