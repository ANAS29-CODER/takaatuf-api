<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBudgetHistoryResource extends JsonResource
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
            'knowledge_request_id' => $this->knowledge_request_id,
            'previous_budget' => number_format((float) $this->previous_budget, 2),
            'new_budget' => number_format((float) $this->new_budget, 2),
            'budget_difference' => number_format($this->budget_difference, 2),
            'previous_pay_per_kp' => $this->previous_pay_per_kp ? number_format((float) $this->previous_pay_per_kp, 2) : null,
            'new_pay_per_kp' => $this->new_pay_per_kp ? number_format((float) $this->new_pay_per_kp, 2) : null,
            'change_type' => $this->change_type,
            'change_type_label' => $this->change_type_label,
            'reason' => $this->reason,
            'admin' => $this->whenLoaded('admin', function () {
                return [
                    'id' => $this->admin->id,
                    'name' => $this->admin->full_name,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
