<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminKnowledgeRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->full_name,
                'email' => $this->creator?->email,
            ],
            'category' => $this->category,
            'details' => $this->details,
            'attachments' => $this->whenLoaded('media', function () {
                return $this->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->url,
                        'type' => $media->type,
                        'original_name' => $media->original_name,
                    ];
                });
            }),
            'total_budget' => number_format((float) $this->total_budget, 2),
            'pay_per_kp' => number_format((float) $this->pay_per_kp, 2),
            'review_fee' => number_format((float) $this->review_fee, 2),
            'number_of_kps' => $this->number_of_kps,
            'kps_assigned' => $this->whenLoaded('knowledgeProviders', function () {
                return $this->knowledgeProviders->count();
            }),
            'kps_still_needed' => $this->kps_still_needed,
            'neighborhood' => $this->neighborhood,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'moderated_by' => $this->whenLoaded('moderator', function () {
                return $this->moderator ? [
                    'id' => $this->moderator->id,
                    'name' => $this->moderator->full_name,
                ] : null;
            }),
            'moderated_at' => $this->moderated_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
