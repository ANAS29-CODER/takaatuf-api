<?php

namespace App\Http\Resources\KP;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RequestDetailResource extends JsonResource
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
            'category' => $this->category,
            'details' => $this->details,
            'neighborhood' => $this->neighborhood,
            'pay_per_kp' => number_format((float) $this->pay_per_kp, 2),
            'pay_per_kp_raw' => (float) $this->pay_per_kp,
            'number_of_kps' => $this->number_of_kps,
            'total_budget' => number_format((float) $this->total_budget, 2),
            'total_budget_raw' => (float) $this->total_budget,
            'status' => $this->status,
            'progress' => $this->progress,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'created_at' => $this->created_at->toDateTimeString(),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => Storage::url($m->file_path),
            ])->toArray()),
        ];
    }
}
