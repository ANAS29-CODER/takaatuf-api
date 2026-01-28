<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingLocationRequest;
use App\Http\Resources\WalletResource;
use App\Models\AuditLog;
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

    public function __construct(
        ProfileService $profileService,
        PayoutService $payoutService,
        WalletService $walletService
    ) {
        $this->profileService = $profileService;
        $this->payoutService = $payoutService;
        $this->walletService = $walletService;
    }

    public function showProfile()
    {
        $user = User::find(auth()->id());
        $response = [
            'name' => $user->full_name,
            'city_neighborhood' => $user->city_neighborhood,
            'profile_completed' => $user->profile_completed,
            'role' => $user->role,
        ];

        if ($user->role === 'Knowledge Requester') {
            $response['paypal_account'] = $user->paypal_account;
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
        try {

            /* =========================
           1️⃣ Validation
        ========================= */
            $data = $request->validate([
                'full_name'         => 'required|string',
                'city_neighborhood' => 'required|string',
                'wallet_type'       => 'nullable|in:ethereum,solana,bitcoin',
                'wallet_address'    => 'nullable|string',
                'paypal_account'    => 'nullable|email',
                'user_confirmation' => 'nullable|in:I am in Gaza,I am outside Gaza',
            ]);

            $user = auth()->user();



            /* =========================
           SECURITY: Prevent role assignment from client
           (Role can only be set by backend logic)
        ========================= */
            if ($request->input('role') !== null) {
                return response()->json([
                    'message' => 'You are not allowed to assign or change your role.'
                ], 403);
            }
            // Extra safety: ensure role is never mass-assigned

            unset($data['role']);

            /* =========================
           2️⃣ Temporarily store wallet inputs
           (Wallet is stored in a separate table)
        ========================= */
            $walletType    = $data['wallet_type'] ?? null;
            $walletAddress = $data['wallet_address'] ?? null;


            /* =========================
           3️⃣ Detect if city is set for the first time or changed
        ========================= */
            $cityChanged = is_null($user->city_neighborhood)
                || $user->city_neighborhood !== $data['city_neighborhood'];

            $role     = $user->role;
            $category = null;
            $location = null;


            /* =========================
           4️⃣ IP-based geolocation check
           (Only when city changes)
        ========================= */
            if ($cityChanged) {

                $locationCheck = $this->profileService->checkLocationMatch(
                    $data['city_neighborhood'],
                    $request->ip()
                );

                $category     = $locationCheck['category']; // Match | Mismatch | Unknown
                $location     = $locationCheck['location'] ?? null;
                $detectedRole = $locationCheck['role'] ?? null;

                // High confidence → auto assign role
                if ($category === 'Match' && $detectedRole) {
                    $role = $detectedRole;
                }
                // Low confidence or unknown → require confirmation
                if (in_array($category, ['Mismatch', 'Unknown'])) {

                    if (empty($data['user_confirmation'])) {
                        return response()->json([
                            'message' => 'We detected your location approximately. Please confirm your location.',
                            'category' => $category,
                            'possible_roles' => [
                                'I am in Gaza',
                                'I am outside Gaza'
                            ],
                        ], 200);
                    }

                    $role = $data['user_confirmation'] === 'I am in Gaza'
                        ? 'Knowledge Provider'
                        : 'Knowledge Requester';
                }

                // Role is assigned ONLY by backend logic

                $data['role'] = $role;
            }




            /* =========================
           5️⃣ Role-based requirements
        ========================= */

            if ($role === 'Knowledge Provider') {
                if (empty($walletType) || empty($walletAddress)) {
                    return response()->json([
                        'message' => 'Wallet information is required for Knowledge Providers.'
                    ], 400);
                }
                // Knowledge Providers do not use PayPal
                $data['paypal_account'] = null;
            }

            if ($role === 'Knowledge Requester') {
                if (empty($data['paypal_account'])) {
                    return response()->json([
                        'message' => 'PayPal account is required for Knowledge Requesters.'
                    ], 400);
                }
            }

            /* =========================
           6️⃣ Update USERS table
           (Profile core data only)
        ========================= */
            unset($data['wallet_type'], $data['wallet_address']);
            $user->update($data);
            /* =========================
           7️⃣ Audit log (only when city changes)
        ========================= */
            if ($cityChanged) {
                AuditLog::create([
                    'user_id'           => $user->id,
                    'location_category' => $category,
                    'location'          => $location['region'] ?? $location['country'] ?? 'Unknown',
                    'user_confirmation' => $data['user_confirmation'] ?? null,
                ]);
            }

            /* =========================
           9️⃣ Compute profile completion
        ========================= */
            if ($role === 'Knowledge Provider') {

                $validation = $this->walletService->validateWalletAddress(
                    $walletType,
                    $walletAddress
                );

                if (!$validation['valid']) {
                    return response()->json([
                        'message' => $validation['message']
                    ], 422);
                }

                $this->walletService->addWallet(
                    $user->id,
                    $walletType,
                    $walletAddress,
                    true
                );
            }

            /* =========================
           9️⃣ Compute profile completion
        ========================= */
            $user->profile_completed =
                $this->profileService->isProfileCompleted($user);
            $user->save();

            /* =========================
           🔟 Response
        ========================= */
            $response = [
                'message' => 'Profile updated successfully.',
                'role' => $user->role,
                'profile_completed' => $user->profile_completed,
            ];

            if ($user->role === 'Knowledge Requester') {
                $response['paypal_account'] = $user->paypal_account;
            }

            if ($user->role === User::KNOWLEDGE_PROVIDER) {
                $wallet = $this->walletService->getPrimaryWallet($user->id);
                $response['primary_wallet'] = $wallet
                    ? new WalletResource($wallet)
                    : null;
            }

            return response()->json($response);
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'error' => 'Failed to update profile.',
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
