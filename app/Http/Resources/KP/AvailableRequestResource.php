<?php

namespace App\Http\Resources\KP;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableRequestResource extends JsonResource
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
            'neighborhood' => $this->neighborhood,
            'payout_amount' => number_format((float) $this->pay_per_kp, 2),
            'payout_amount_raw' => (float) $this->pay_per_kp,
            'kps_still_needed' => $this->kps_still_needed ?? $this->number_of_kps,
            'total_kps_needed' => $this->number_of_kps,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
