<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\PayoutService;
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
        $data = [
            'name' => $this->full_name,
            'city_neighborhood' => $this->city_neighborhood,
            'role' => $this->role,
            'profile_completed' => $this->profile_completed,
        ];
        if ($this->role === User::KNOWLEDGE_REQUESTER) {
            $data['paypal_account'] = $this->paypal_account;
        } elseif ($this->role === User::KNOWLEDGE_PROVIDER) {
            $data['wallet_type'] = $this->wallet_type;
            $data['wallet_address'] = $this->wallet_address;

            $payoutService = app(PayoutService::class);
            $currentEarnings = $payoutService->getCurrentEarnings($this->id);
            $payoutStatus = $payoutService->canRequestPayout($this->id);

            $data['current_earnings'] = number_format($currentEarnings, 2);
            $data['can_request_payout'] = $payoutStatus['can_request'];

            if (!$payoutStatus['can_request']) {
                $data['payout_minimum_message'] = $payoutStatus['reason'];
            }

            $data['total_historical_payouts'] = number_format(
                $payoutService->getTotalPayoutsAmount($this->id),
                2
            );

            $data['payout_history']= $payoutService->getPayoutHistory($this->id);
        }

        return $data;
    }
}
