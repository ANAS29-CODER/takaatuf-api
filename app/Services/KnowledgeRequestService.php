<?php

namespace App\Services;

use App\Models\KnowledgeRequest;
use App\Models\User;
use App\Repositories\KnowledgeRequestRepository;

class KnowledgeRequestService
{
    public function __construct(
        protected KnowledgeRequestRepository $repository
    ) {}

    public function create(array $data, User $user)
    {
        $data['user_id'] = $user->id;

        $data['total_budget'] =
            ($data['pay_per_kp'] * $data['number_of_providers']) + 5;

        $attachments = $data['attachments'] ?? [];
        unset($data['attachments']);

        $request = $this->repository->create($data);

        foreach ($attachments as $file) {
            $type = str_contains($file->getMimeType(), 'video')
                ? 'video'
                : 'image';

            if ($type === 'image' && $file->getSize() > 10 * 1024 * 1024) {
                throw new \DomainException('Image exceeds 10MB');
            }

            if ($type === 'video' && $file->getSize() > 100 * 1024 * 1024) {
                throw new \DomainException('Video exceeds 100MB');
            }

            $path = $file->store('knowledge_requests');

            $request->attachments()->create([
                'type' => $type,
                'path' => $path,
                'size' => $file->getSize(),
            ]);
        }

        return $request;
    }
}

