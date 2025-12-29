<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'profile_completed' => (bool) $this->profile_completed,
            'role' => $this->role,
            'city_neighborhood' => $this->city_neighborhood,
            'wallet_type' => $this->wallet_type,
            'wallet_address' => $this->wallet_address,
            'paypal_account' => $this->paypal_account,
        ];
    }
}
