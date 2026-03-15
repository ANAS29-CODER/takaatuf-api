<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingLocationRequest;
use App\Http\Resources\WalletResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayoutService;
use App\Services\PayPalService;
use App\Services\ProfileService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $user = User::find(auth()->id());
        $response = [
            'id' =>$user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'city_neighborhood' => $user->city_neighborhood,
            'profile_completed' => $user->profile_completed,
            'role' => $user->role,
        ];

        if ($user->role === 'Knowledge Requester') {
            $response['paypal_account'] = $user->paypal_account;
            $response['paypal_status'] = $this->paypalService->getAccountStatus($user)['status'];
           $response['paypal_email'] = $user->paypalAccount?->paypal_email;

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

            $response['payout_history'] = $this->payoutService->getPayoutHistory($user->id)->map(function ($payout) {
                return [
                    'amount' => $payout->amount,
                    'requested_date'   => $payout->created_at,
                    'payout_at'        => $payout->payout_at,
                    'wallet'           => $payout->getWalletAddressLastFourAttribute(),
                    'status'           => $payout->status
                ];
            });
        }
        // 🔹 Profile status helpers (for frontend routing)
        if (!$user->profile_completed) {
            $response['status'] = 'profile_incomplete';
            $response['message'] = 'Please complete your profile to continue.';
        } elseif ($user->profile_completed && is_null($user->role)) {
            $response['status'] = 'location_confirmation_required';
            $response['message'] = 'Please confirm whether you are in Gaza or outside Gaza.';
        } else {
            $response['status'] = 'profile_complete';
        }

        return response()->json($response);
    }


        public function updateProfile(Request $request)
    {
        $user = User::find(auth()->id());

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // ========================
        // Knowledge Requester (KR)
        // ========================
        if ($user->role === 'Knowledge Requester') {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|min:2|max:100',
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $updated = false;

            if ($user->full_name !== $request->full_name) {
                $user->full_name = $request->full_name;
                $updated = true;
            }

            if ($user->email !== $request->email) {
                $user->email = $request->email;
                $updated = true;
            }

            if (!$updated) {
                return response()->json(['message' => 'No changes to save'], 200);
            }

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => [
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ], 200);
        }

        // ========================
        // Knowledge Provider (KP)
        // ========================
        if ($user->role === 'Knowledge Provider') {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|min:2|max:100',
                'city_neighborhood' => 'required|string|max:255',
                'wallet_address' => 'nullable|string|max:255', // تحديث محفظة موجودة
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $updated = false;

            if ($user->full_name !== $request->full_name) {
                $user->full_name = $request->full_name;
                $updated = true;
            }

            if ($user->city_neighborhood !== $request->city_neighborhood) {
                $user->city_neighborhood = $request->city_neighborhood;
                $updated = true;
            }

            $user->save();

            if ($request->wallet_address) {
                $primaryWallet = Wallet::where('user_id', $user->id)
                    ->where('is_primary', true)
                    ->first();

                if ($primaryWallet && $primaryWallet->wallet_address !== $request->wallet_address) {
                    $primaryWallet->wallet_address = $request->wallet_address;
                    $primaryWallet->save();
                    $updated = true;
                }
            }

            if (!$updated) {
                return response()->json(['message' => 'No changes to save'], 200);
            }

            // إعادة البيانات مع المحفظة
            $primaryWallet = Wallet::where('user_id', $user->id)->where('is_primary', true)->first();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => [
                    'full_name' => $user->full_name,
                    'city_neighborhood' => $user->city_neighborhood,
                    'primary_wallet' => $primaryWallet ? [
                        'wallet_type' => $primaryWallet->wallet_type,
                        'wallet_address' => $primaryWallet->wallet_address,
                        'is_primary' => $primaryWallet->is_primary,
                    ] : null,
                ]
            ], 200);
        }

        return response()->json(['message' => 'User role not supported'], 400);
    }
    
     public function completeProfile(Request $request)
{
    $user = auth()->user();

    $data = $request->validate([
        'full_name' => 'required|string|max:255',
        'city_neighborhood' => 'required|string|max:255',
    ]);

    $user->update([
        'full_name' => $data['full_name'],
        'city_neighborhood' => $data['city_neighborhood'],
    ]);

    $locationCheck = $this->profileService->checkLocationMatch(
        $data['city_neighborhood'],
        $request->ip()
    );

    $category = $locationCheck['category']; // Match | Mismatch | Unknown
    $detectedRole = $locationCheck['role'] ?? null;
    $location = $locationCheck['location'] ?? null;

    $role = null;

    if ($category === 'Match' && $detectedRole) {
        $role = $detectedRole;
        $user->update(['role' => $role]);
    }
    $this->profileService->storeAuditLog(
        $user->id,
        $category,
        $location,
         'complete_profile' ,
        $request->input('user_confirmation', null)
    );
    return response()->json([
        'message' => 'Profile updated successfully.',
        'status' => $category,
        'id' => $user->id,
        'name' => $user->full_name,
        'email' => $user->email,
        'email_verified' => $user->hasVerifiedEmail(),
        'role' => $user->role,
        'city' => $user->city_neighborhood,
        'profile_completed' => $user->profile_completed,
    ], 200);
}
  public function confirmLocation(Request $request)
{
     $data = $request->validate([
        'user_confirmation' => 'required|in:IN_GAZA,OUTSIDE_GAZA',
    ]);

    $user = auth()->user();

    $role = $data['user_confirmation'] === 'IN_GAZA'
        ? 'Knowledge Provider'
        : 'Knowledge Requester';

    $user->update([
        'role' => $role
    ]);

    $audit = AuditLog::where('user_id', $user->id)
    ->whereNull('user_confirmation')
    ->latest()
    ->first();

    if ($audit) {
        $audit->update([
            'user_confirmation' => $data['user_confirmation'],
        ]);
    }

    return response()->json([
        'message' => 'Location confirmed.',
        'id' => $user->id,
        'full_name' => $user->full_name,
        'email' => $user->email,
        'email_verified' => $user->hasVerifiedEmail(),
        'role' => $user->role,
        'city_neighborhood' => $user->city_neighborhood,
        'profile_completed' => false,
    ], 200);
}

public function updatePayment(Request $request)
{
    $user = auth()->user();

    if ($user->role === 'Knowledge Requester') {
        $data = $request->validate([
            'paypal_email' => 'required|email'
        ]);


        $paypal = $user->paypalAccount;
        if ($paypal) {
            $paypal->update([
                'paypal_email' => $data['paypal_email'],
            ]);
        } else {
            $user->paypalAccount()->create([
                'paypal_email' => $data['paypal_email'],
            ]);
        }

          $user->update([
        'profile_completed' => true
    ]);

        return response()->json([
            'message' => 'PayPal email saved successfully.',
               'id' => $user->id,
                'role' => $user->role,
            'profile_completed' => true
        ]);
    }

    if ($user->role === 'Knowledge Provider') {
        $data = $request->validate([
            'wallet_type' => 'required|in:ethereum,solana,bitcoin',
            'wallet_address' => 'required|string',
        ]);

        $validation = $this->walletService->validateWalletAddress(
            $data['wallet_type'],
            $data['wallet_address']
        );

        if (!$validation['valid']) {
            return response()->json([
                'message' => $validation['message']
            ], 422);
        }

        $this->walletService->addWallet(
            $user->id,
            $data['wallet_type'],
            $data['wallet_address'],
            true
        );

          $user->update([
        'profile_completed' => true
    ]);

        return response()->json([
            'message' => 'Wallet saved successfully.',
               'id' => $user->id,
                 'role' => $user->role,
            'profile_completed' => true
        ]);
    }

    return response()->json([
        'message' => 'Invalid role for payment/wallet'
    ], 403);
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
