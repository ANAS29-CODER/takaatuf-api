<?php

namespace App\Http\Resources;

use App\Models\Wallet;
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
            'wallet_address' => Wallet::where('id', $this->wallet_id)->value('wallet_address'),
            'wallet_type' => $this->wallet_type,
            'status' => $this->status,
            'payout_at' => $this->payout_at ? $this->payout_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
