<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
  public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'location_category' => $this->location_category,
            'location' => json_decode($this->location), 
            'user_confirmation' => $this->user_confirmation,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
