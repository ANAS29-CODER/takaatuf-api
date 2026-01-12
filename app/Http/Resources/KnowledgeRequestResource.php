<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeRequestResource extends JsonResource
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
            'category' => $this->category,
            'details' => $this->details,
            'pay_per_kp' => $this->pay_per_kp,
            'number_of_providers' => $this->number_of_providers,
            'total_budget' => $this->total_budget,
            'neighborhood' => $this->neighborhood,
            'status' => $this->status,
            'attachments' => $this->attachments->map(fn ($a) => [
                'type' => $a->type,
                'url' => asset('storage/' . $a->path),
            ]),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
