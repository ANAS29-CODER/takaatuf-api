<?php

namespace App\Http\Resources\KP;

use App\Models\UserKnowledgeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveRequestResource extends JsonResource
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
            'category' => $this->category,
            'title' => $this->getTitle(),
            'details' => $this->details,
            'neighborhood' => $this->neighborhood,
            'payout_amount' => number_format((float) ($this->kp_payout_amount ?? $this->pay_per_kp), 2),
            'payout_amount_raw' => (float) ($this->kp_payout_amount ?? $this->pay_per_kp),
            'progress' => $this->kp_progress ?? 0,
            'status' => $this->kp_status ?? UserKnowledgeRequest::STATUS_PENDING,
            'status_label' => $this->getStatusLabel(),
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Get a short title/description for the request
     */
    protected function getTitle(): string
    {
        if ($this->details) {
            return strlen($this->details) > 60
                ? substr($this->details, 0, 60) . '...'
                : $this->details;
        }

        return $this->category . ' Request';
    }

    /**
     * Get human-readable status label
     */
    protected function getStatusLabel(): string
    {
        return match ($this->kp_status ?? UserKnowledgeRequest::STATUS_PENDING) {
            UserKnowledgeRequest::STATUS_PENDING => 'Pending',
            UserKnowledgeRequest::STATUS_IN_PROGRESS => 'In Progress',
            UserKnowledgeRequest::STATUS_AWAITING_REVIEW => 'Awaiting Review',
            default => ucfirst(str_replace('_', ' ', $this->kp_status ?? 'pending')),
        };
    }
}
