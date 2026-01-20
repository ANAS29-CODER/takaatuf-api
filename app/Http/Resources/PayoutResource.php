<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
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
            'amount' => number_format((float) $this->amount, 2),
            'payout_date' => $this->created_at->format('Y-m-d'),
            'wallet_address' => Wallet::where(),
            'wallet_type' => $this->wallet_type,
            'status' => $this->status,
            'processed_at' => $this->processed_at ? $this->processed_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
