<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaypalAccountResource;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PayPalController extends Controller
{
    protected PayPalService $payPalService;

    public function __construct(PayPalService $payPalService)
    {
        $this->payPalService = $payPalService;
    }

    /**
     * Get PayPal account status for the authenticated user
     */
    public function status(): JsonResponse
    {
        $user = auth()->user();
        $status = $this->payPalService->getAccountStatus($user);

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Get PayPal account details
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $paypalAccount = $user->paypalAccount;

        if (!$paypalAccount) {
            return response()->json([
                'data' => null,
                'message' => 'No PayPal account linked.',
            ]);
        }

        return response()->json([
            'data' => new PaypalAccountResource($paypalAccount),
        ]);
    }

    public function connect(): JsonResponse
    {
        $user = auth()->user();

        try {
            $state = $this->payPalService->generateState($user->id);

            // Store state in cache for validation (10 minutes)
            Cache::put("paypal_state_{$user->id}", $state, 600);

            $authorizationUrl = $this->payPalService->getAuthorizationUrl($state);

            return response()->json([
                'authorization_url' => $authorizationUrl,
                'message' => 'Redirect user to this URL to connect their PayPal account.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate PayPal connection. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }


    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        // Validate state parameter
        $stateData = $this->payPalService->validateState($validated['state']);

        if (!$stateData) {
            return response()->json([
                'message' => 'Invalid or expired authorization request. Please try again.',
            ], 400);
        }

        $userId = $stateData['user_id'];
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $storedState = Cache::get("paypal_state_{$userId}");
        if ($storedState !== $validated['state']) {
            return response()->json([
                'message' => 'Authorization state mismatch. Please try again.',
            ], 400);
        }

        // Clear the state from cache
        Cache::forget("paypal_state_{$userId}");

        try {
            $result = $this->payPalService->connectAccount($user, $validated['code']);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'message' => $result['message'],
                'data' => new PaypalAccountResource($result['paypal_account']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to connect PayPal account. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update PayPal email manually (without OAuth)
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_email' => 'required|email|max:255',
        ]);

        $user = auth()->user();

        try {
            $result = $this->payPalService->updatePayPalEmail($user, $validated['paypal_email']);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'message' => $result['message'],
                'data' => new PaypalAccountResource($result['paypal_account']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update PayPal email. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Disconnect PayPal account
     */
    public function disconnect(): JsonResponse
    {
        $user = auth()->user();

        try {
            $result = $this->payPalService->disconnectAccount($user);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to disconnect PayPal account. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
