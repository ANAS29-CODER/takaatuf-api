<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray($request)
    {
        return [
            'name' => $this->name,
            'city_neighborhood' => $this->city_neighborhood,
            'wallet_type' => $this->wallet_type,
            'wallet_address' => $this->wallet_address,
            'paypal_account' => $this->paypal_account,
            'role' => $this->role,  
            'profile_completed' => $this->profile_completed,
        ];
    }
}
