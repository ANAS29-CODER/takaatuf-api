<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pending_requests' => $this->resource['pending_requests'],
            'approved_requests' => $this->resource['approved_requests'],
            'rejected_requests' => $this->resource['rejected_requests'],
            'pending_payouts' => $this->resource['pending_payouts'],
            'completed_payouts' => $this->resource['completed_payouts'],
            'total_kps' => $this->resource['total_kps'],
            'total_krs' => $this->resource['total_krs'],
            'pending_submissions' => $this->resource['pending_submissions'],
            'total_earnings' => number_format((float) $this->resource['total_earnings'], 2),
            'total_payouts_amount' => number_format((float) $this->resource['total_payouts_amount'], 2),
        ];
    }
}
