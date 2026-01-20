<?php
// app/Services/KnowledgeRequestService.php

namespace App\Services;

use App\Models\KnowledgeRequestMedia;

use App\Repositories\KnowledgeRequestRepository;
use App\Repositories\KnowledgeRequestMediaRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class KnowledgeRequestService
{
    protected $requests;
    protected $media;

    public function __construct(
        KnowledgeRequestRepository $requests,
        KnowledgeRequestMediaRepository $media
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

    public function storeMedia(int $requestId, array|UploadedFile $files): Collection
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $savedMedia = collect();

        foreach ($files as $file) {

            $path = $file->store('knowledge_requests/media', 'public');

            $type = str_contains($file->getMimeType(), 'image')
                ? 'image'
                : 'video';

            $media = $this->media->create([
                'knowledge_request_id' => $requestId,
                'file_path' => $path,
                'type' => $type,
            ]);

            $savedMedia->push($media);
        }

        return $savedMedia;
    }

    public function getActiveRequests($user)
    {
        return $this->requests->getActiveRequests($user);
    }

    public function getCompletedRequests($user)
    {
        return $this->requests->getCompletedRequests($user);
    }
}
