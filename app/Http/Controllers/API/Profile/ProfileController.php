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

    public function showProfile()
    {
        $user = auth()->user();

        $response = [
            'full_name' => $user->full_name,
            'city_neighborhood' => $user->city_neighborhood,
            'profile_completed' => $user->profile_completed,
            'role' => $user->role,
        ];

        if ($user->role === 'Knowledge Requester') {
            $response['paypal_account'] = $user->paypal_account;
        } elseif ($user->role === 'Knowledge Provider') {
            $response['wallet_address'] = $user->wallet_address;
            $response['wallet_type'] = $user->wallet_type;
        }

        return response()->json($response);
    }

    public function updateProfile(Request $request)
    {
        try {
            $data = $request->validate([
                'full_name' => 'required|string|max:255',
                'city_neighborhood' => 'required|string|max:255',
                'wallet_type' => 'nullable|string|in:ethereum,solana,bitcoin',
                'wallet_address' => 'nullable|string',
                'paypal_account' => 'nullable|email',
                'user_confirmation' => 'nullable|string',
            ]);


            $ip = $request->ip();
            $locationCheck = $this->profileService->checkLocationMatch($data['city_neighborhood'], $ip);

            $category = $locationCheck['category'];
            $role = $locationCheck['role'];
            $location = $locationCheck['location'];

            if (!$role) {
                if (empty($data['user_confirmation'])) {
                    return response()->json([
                        'message' => 'Please confirm your location',
                        'category' => $category,
                        'ip_region' => $location['region'] ?? 'Unknown',
                        'possible_roles' => ['I am in Gaza', 'I am outside Gaza'], // يمكن إضافة الخيارات هنا لتوضيح للمستخدم
                    ], 200);
                }

                $role = ($data['user_confirmation'] === 'I am in Gaza') ? 'Knowledge Provider' : 'Knowledge Requester';
            }


            $data['role'] = $role;
            if ($role === 'Knowledge Provider' && (empty($data['wallet_type']) || empty($data['wallet_address']))) {
                return response()->json(['message' => 'Please complete the required wallet fields.'], 400);
            }

            if ($role === 'Knowledge Requester' && empty($data['paypal_account'])) {
                return response()->json(['message' => 'Please provide your PayPal account.'], 400);
            }

            $user = $this->profileService->updateProfile(auth()->id(), $data);
            $user->profile_completed = true;
            $user->save();

            $this->profileService->storeAuditLog(auth()->id(), $category, $location, $data['user_confirmation'] ?? null);

            $response = [
                // 'full_name' => $user->full_name,
                // 'city_neighborhood' => $user->city_neighborhood,
                  'message' => 'Profile has been updated successfully!',
                'profile_completed' => $user->profile_completed,
                'role' => $user->role,
            ];

            if ($user->role === 'Knowledge Requester') {
                $response['paypal_account'] = $user->paypal_account;
            }

            if ($user->role === 'Knowledge Provider') {
                $response['wallet_type'] = $user->wallet_type;
                $response['wallet_address'] = $user->wallet_address;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'error' => 'An error occurred while saving your profile.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
