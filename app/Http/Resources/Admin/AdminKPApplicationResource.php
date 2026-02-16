<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminKPApplicationResource extends JsonResource
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
            'kp' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->full_name,
                    'email' => $this->user->email,
                    'location' => $this->user->city_neighborhood,
                ];
            }),
            'knowledge_request' => $this->whenLoaded('knowledgeRequest', function () {
                return [
                    'id' => $this->knowledgeRequest->id,
                    'category' => $this->knowledgeRequest->category,
                    'details' => $this->knowledgeRequest->details,
                    'pay_per_kp' => number_format((float) $this->knowledgeRequest->pay_per_kp, 2),
                ];
            }),
            'status' => $this->status,
            'payout_amount' => $this->payout_amount ? number_format((float) $this->payout_amount, 2) : null,
            'progress' => $this->progress,
            'applied_at' => $this->created_at->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
        ];
    }
}
