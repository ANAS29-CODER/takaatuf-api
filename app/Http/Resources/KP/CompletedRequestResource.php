<?php

namespace App\Http\Resources\KP;

use App\Models\UserKnowledgeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompletedRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completedAt = $this->completed_at ?? $this->updated_at;

        return [
            'id' => $this->id,
            'category' => $this->category,
            'neighborhood' => $this->neighborhood,
            'payout_amount' => number_format((float) ($this->kp_payout_amount ?? $this->pay_per_kp), 2),
            'payout_amount_raw' => (float) ($this->kp_payout_amount ?? $this->pay_per_kp),
            'completion_date' => $completedAt instanceof \Carbon\Carbon
                ? $completedAt->format('Y-m-d')
                : date('Y-m-d', strtotime($completedAt)),
            'status' => $this->kp_status ?? UserKnowledgeRequest::STATUS_COMPLETED,
            'final_status' => $this->getFinalStatus(),
        ];
    }

    /**
     * Get the final display status
     */
    protected function getFinalStatus(): string
    {
        // Check if this has been paid out (you may need to adjust this logic
        // based on how payments are tracked in your system)
        $isPaid = false; // This could be determined by checking earnings/payouts

        if ($isPaid) {
            return 'Paid';
        }

        return match ($this->kp_status ?? UserKnowledgeRequest::STATUS_COMPLETED) {
            UserKnowledgeRequest::STATUS_APPROVED => 'Approved',
            UserKnowledgeRequest::STATUS_COMPLETED => 'Completed',
            default => 'Completed',
        };
    }
}
