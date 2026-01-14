<?php
namespace App\Repositories;

use App\Models\KnowledgeRequestMedia;

class KnowledgeRequestMediaRepository
{
      public function create(array $data): KnowledgeRequestMediaRepository
    {
        return KnowledgeRequestMedia::create($data);
    }
}
?>
