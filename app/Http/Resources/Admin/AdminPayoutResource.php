<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutResource extends JsonResource
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
            'kp' => [
                'id' => $this->user?->id,
                'name' => $this->user?->full_name,
                'email' => $this->user?->email,
            ],
            'amount' => number_format((float) $this->amount, 2),
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'id' => $this->wallet->id,
                    'type' => $this->wallet->wallet_type,
                    'address' => $this->wallet->wallet_address,
                ];
            }),
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'admin_notes' => $this->admin_notes,
            'processed_by' => $this->whenLoaded('processor', function () {
                return $this->processor ? [
                    'id' => $this->processor->id,
                    'name' => $this->processor->full_name,
                ] : null;
            }),
            'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
            'requested_at' => $this->created_at->format('Y-m-d H:i:s'),
            'payout_at' => $this->payout_at?->format('Y-m-d H:i:s'),
        ];
    }
}
