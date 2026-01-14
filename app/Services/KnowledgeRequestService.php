<?php
// app/Services/KnowledgeRequestService.php

namespace App\Services;

use App\Repositories\KnowledgeRequestMediaRepository as RepositoriesKnowledgeRequestMediaRepository;
use App\Repositories\KnowledgeRequestRepository;
use Illuminate\Support\Facades\Storage;
use KnowledgeRequestMediaRepository;

class KnowledgeRequestService
{
    protected $requests;
    protected $media;

    public function __construct(
        KnowledgeRequestRepository $requests,
        RepositoriesKnowledgeRequestMediaRepository $media
    ) {
        $this->requests = $requests;
        $this->media = $media;
    }

    public function calculateBudget(float $pay, int $count): float
    {
        return ($pay * $count) + 5;
    }

    public function createRequest(array $data)
    {
        return $this->requests->create($data);
    }

    public function storeMedia(int $requestId, array $files)
    {
        foreach ($files as $file) {
            $mime = $file->getMimeType();
            $type = str_contains($mime, 'video') ? 'video' : 'image';

            $path = $file->store('knowledge_requests', 'public');

            $this->media->create([
                'knowledge_request_id' => $requestId,
                'file_path' => $path,
                'type' => $type,
            ]);
        }
    }
}
