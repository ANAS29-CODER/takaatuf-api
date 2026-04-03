<?php

namespace App\Http\Resources\KP;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RejectedRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'details' => $this->details,
            'neighborhood' => $this->neighborhood,
            'payout_amount' => number_format((float) ($this->kp_payout_amount ?? $this->pay_per_kp), 2),
            'payout_amount_raw' => (float) ($this->kp_payout_amount ?? $this->pay_per_kp),
            'status' => $this->kp_status,
            'status_label' => 'Rejected',
            'due_date' => $this->due_date?->format('Y-m-d'),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
