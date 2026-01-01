<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showProfile()
    {
        $user = auth()->user();

        $response = [
            'name' => $user->name,
            'city_neighborhood' => $user->city_neighborhood,
            'profile_completed' => $user->profile_completed,
            'role' => $user->role,
        ];

        if ($user->role === 'Knowledge Requester') {
            $response['paypal_account'] = $user->paypal_account;
        } elseif ($user->role === 'Knowledge Provider') {
            $response['wallet_address'] = $user->wallet_address;
        }

        return response()->json($response);
    }

    /**
     *
     * update profile
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {

            $data = $request->validate([
                'name' => 'required|string',
                'city_neighborhood' => 'required|string',
                'wallet_type' => 'nullable|string',
                'wallet_address' => 'nullable|string',
                'paypal_account' => 'nullable|string',
            ]);

            $role = $this->profileService->assignRoleBasedOnLocation($data['city_neighborhood']);
            $data['role'] = $role;

            // التحقق من صحة البيانات بناءً على الدور
            if ($role == 'Knowledge Provider' && (empty($data['wallet_type']) || empty($data['wallet_address']))) {
                return response()->json(['message' => 'Please complete the required wallet fields.'], 400);
            }

            if ($role == 'Knowledge Requester' && empty($data['paypal_account'])) {
                return response()->json(['message' => 'Please provide your PayPal account.'], 400);
            }


            if ($role == 'Knowledge Provider') {

                if ($data['wallet_type'] == 'Ethereum' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $data['wallet_address'])) {
                    return response()->json(['message' => 'Invalid Ethereum wallet address format.'], 400);
                } elseif ($data['wallet_type'] == 'Bitcoin' && !preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $data['wallet_address'])) {
                    return response()->json(['message' => 'Invalid Bitcoin wallet address format.'], 400);
                }
            }

            $user = $this->profileService->updateProfile(auth()->id(), $data);

            $user->profile_completed = true;
            $user->save();

            $response = [
                'name' => $user->name,
                'city_neighborhood' => $user->city_neighborhood,
                'profile_completed' => $user->profile_completed,
                'role' => $user->role,
            ];

            if ($user->role == 'Knowledge Requester') {
                $response['paypal_account'] = $user->paypal_account;
            }

            if ($user->role == 'Knowledge Provider') {
                $response['wallet_type'] = $user->wallet_type;
                $response['wallet_address'] = $user->wallet_address;
            }

            return response()->json($response);
        } catch (\Exception $e) {

            return response()->json(['error' => 'An error occurred while saving your profile. Please try again later.'], 500);
        }
    }
}
