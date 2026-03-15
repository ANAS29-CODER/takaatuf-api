<?php

namespace App\Http\Resources\KP;

use App\Http\Resources\WalletResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class KPProfileResource extends JsonResource
{
    /**
     * @var array<string, mixed>
     */
    protected array $earningsData = [];

    /**
     * @param  array<string, mixed>  $earningsData
     */
    public function setEarningsData(array $earningsData): static
    {
        $this->earningsData = $earningsData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'email' => $this->email,
            'city_neighborhood' => $this->city_neighborhood,
            'profile_completed' => (bool) $this->profile_completed,
            'role' => $this->role,
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,

            'primary_wallet' => $this->whenLoaded('primaryWallet', function () {
                return new WalletResource($this->primaryWallet);
            }),
            'wallets' => WalletResource::collection($this->whenLoaded('wallets')),

            'current_earnings' => $this->earningsData['current_earnings'] ?? null,
            'can_request_payout' => $this->earningsData['can_request_payout'] ?? null,
            'payout_minimum_message' => $this->earningsData['payout_minimum_message'] ?? null,
            'total_historical_payouts' => $this->earningsData['total_historical_payouts'] ?? null,
            'payout_history' => $this->earningsData['payout_history'] ?? [],

            'profile_status' => $this->getProfileStatus(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getProfileStatus(): array
    {
        if (! $this->profile_completed) {
            return [
                'status' => 'profile_incomplete',
                'message' => 'Please complete your profile to continue.',
            ];
        }

        if ($this->profile_completed && is_null($this->role)) {
            return [
                'status' => 'location_confirmation_required',
                'message' => 'Please confirm whether you are in Gaza or outside Gaza.',
            ];
        }

        return ['status' => 'profile_complete'];
    }
}
