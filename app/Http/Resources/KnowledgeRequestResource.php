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
        'id'            => $this->id,
        'category'      => $this->category,
        'details'       => $this->details,
        'pay_per_kp'    => $this->pay_per_kp,
        'number_of_kps' => $this->number_of_kps,
        'total_budget'  => $this->total_budget,
        'neighborhood'  => $this->neighborhood,
        'status'        => $this->status,
        'progress'      => $this->status === 'completed' ? 100 : ($this->progress ?? 0),
        'media' => $this->whenLoaded('media', function () {
            return $this->media->map(function ($m) {
                return [
                    'id'   => $m->id,
                    'type' => $m->type,
                    'url'  => asset('storage/' . $m->file_path),
                ];
            });
        }),
        'kp_progress' => $this->whenLoaded('knowledgeProviders', function () {
            return $this->knowledgeProviders->map(function ($kp) {
                return [
                    'kp_id'    => $kp->id,
                    'name'     => $kp->full_name,
                    'progress' => $kp->pivot->progress ?? 0,
                    'status'   => $kp->pivot->status,
                ];
            });
        }),
        'created_at' => $this->created_at->toDateTimeString(),
    ];
}

}
