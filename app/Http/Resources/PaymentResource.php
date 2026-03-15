<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_id' => $this->reference_id,
            'knowledge_request_id' => $this->knowledge_request_id,
            'amount' => $this->amount,
            'system_fee' => $this->system_fee,
            'payment_fee' => $this->payment_fee,
            'total' => $this->total,
            'currency' => 'USD',
            'status' => $this->status,
            'payer_email' => $this->payer_email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
