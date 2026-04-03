<?php

namespace App\Http\Resources\KR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class KRProfileResource extends JsonResource
{
    /**
     * @var array<string, mixed>
     */
    protected array $paypalData = [];

    /**
     * @param  array<string, mixed>  $paypalData
     */
    public function setPaypalData(array $paypalData): static
    {
        $this->paypalData = $paypalData;

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

            'paypal_email' => $this->paypalAccount?->paypal_email,
            'paypal_status' => $this->paypalData['status'] ?? null,

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
