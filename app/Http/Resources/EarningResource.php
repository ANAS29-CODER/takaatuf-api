<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EarningResource extends JsonResource
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
            'description' => $this->description,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
