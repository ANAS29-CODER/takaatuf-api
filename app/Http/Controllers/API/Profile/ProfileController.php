<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingLocationRequest;
use App\Http\Resources\WalletResource;
use App\Models\User;
use App\Services\PayoutService;
use App\Services\ProfileService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected $profileService;
    protected $payoutService;
    protected $walletService;
    protected $paypalService;

    public function __construct(
        ProfileService $profileService,
        PayoutService $payoutService,
        WalletService $walletService,
        PayPalService $paypalService
    ) {
        $this->profileService = $profileService;
        $this->payoutService = $payoutService;
        $this->walletService = $walletService;
        $this->paypalService = $paypalService;
    }

    public function showProfile()
    {
        $user = auth()->user();

        if (!$user->profile_completed) {
            return response()->json([
                'message' => 'Your profile is incomplete. Please complete your profile to access all features.',
            ], 400);
        }

        $response = [
            'name' => $user->full_name,
            'email' => $user->email,
            'city_neighborhood' => $user->city_neighborhood,
            'profile_completed' => $user->profile_completed,
            'role' => $user->role,
        ];


        if ($user->role === 'Knowledge Requester') {
            $response['paypal_account'] = $user->paypal_account;
            $response['paypal_status'] = $this->profileService->getPayPalStatus($user);
            $response['paypal_email'] = $user->paypalAccount?->email;
        } elseif ($user->role === User::KNOWLEDGE_PROVIDER) {
            // Get primary wallet from wallets table
            $primaryWallet = $this->walletService->getPrimaryWallet($user->id);

            if ($primaryWallet) {
                $response['primary_wallet'] = new WalletResource($primaryWallet);
            } else {
                $response['primary_wallet'] = null;
            }

            // Get all wallets
            $wallets = $this->walletService->getUserWallets($user->id);
            $response['wallets'] = WalletResource::collection($wallets);

            // Add earnings and payout information for Knowledge Providers
            $currentEarnings = $this->payoutService->getCurrentEarnings($user->id);
            $payoutStatus = $this->payoutService->canRequestPayout($user->id);

            $response['current_earnings'] = number_format($currentEarnings, 2);
            $response['can_request_payout'] = $payoutStatus['can_request'];

            if (!$payoutStatus['can_request']) {
                $response['payout_minimum_message'] = $payoutStatus['reason'];
            }

            $response['total_historical_payouts'] = number_format(
                $this->payoutService->getTotalPayoutsAmount($user->id),
                2
            );

            $response['payout_history']= $this->payoutService->getPayoutHistory($user->id)->map(function($payout){
             return [
                'amount' => $payout->amount,
                'requested_date'   => $payout->created_at,
                'payout_at'        => $payout->payout_at,
                'wallet'           => $payout->getWalletAddressLastFourAttribute(),
                'status'           => $payout->status
             ];

            });
        }

        return response()->json($response);
    }

    public function updateProfile(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string',
                'city_neighborhood' => 'required|string',
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
                        'possible_roles' => ['I am in Gaza', 'I am outside Gaza'],
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

            $walletData = null;
            if ($role == 'Knowledge Provider' && !empty($data['wallet_type']) && !empty($data['wallet_address'])) {
                $validation = $this->walletService->validateWalletAddress($data['wallet_type'], $data['wallet_address']);
                if (!$validation['valid']) {
                    return response()->json([
                        'message' => $validation['message'],
                    ], 422);
                }
                $walletData = [
                    'wallet_type' => $data['wallet_type'],
                    'wallet_address' => $data['wallet_address'],
                ];
            }
            unset($data['wallet_type'], $data['wallet_address']);

            $user = $this->profileService->updateProfile(auth()->id(), $data);
            $user->profile_completed = true;
            $user->save();

            if ($walletData) {
                $this->walletService->addWallet(
                    $user->id,
                    $walletData['wallet_type'],
                    $walletData['wallet_address'],
                    true // Set as primary
                );
            }

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

            if ($user->role === User::KNOWLEDGE_PROVIDER) {
                $primaryWallet = $this->walletService->getPrimaryWallet($user->id);
                $response['primary_wallet'] = $primaryWallet ? new WalletResource($primaryWallet) : null;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'error' => 'An error occurred while saving your profile.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }


    public function updateWorkingLocation(UpdateWorkingLocationRequest $request)
    {
        try {
            $result = $this->profileService->updateWorkingLocation(
                auth()->id(),
                $request->input('city_neighborhood')
            );

            return response()->json([
                'message' => $result['message'],
                'city_neighborhood' => $result['user']->city_neighborhood,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while updating your working location.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
