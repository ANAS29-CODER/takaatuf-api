<?php

namespace App\Http\Resources;

use App\Models\PaypalAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaypalAccountResource extends JsonResource
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
            'paypal_email' => $this->paypal_email,
            'status' => $this->status,
            'is_authenticated' => $this->paypal_account_id !== null && $this->status === PaypalAccount::STATUS_CONNECTED,
            'connected_at' => $this->status === PaypalAccount::STATUS_CONNECTED ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
