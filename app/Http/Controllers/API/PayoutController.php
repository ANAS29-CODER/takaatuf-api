<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    protected PayoutService $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;

    }

    /**
     * Get payout history for the authenticated user
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $perPage = $request->input('per_page', 15);
        $payouts = $this->payoutService->getPayoutHistory($user->id, $perPage);

        if ($payouts->isEmpty()) {
            return response()->json([
                'message' => 'No payouts yet.',
                'data' => [],
                'total_historical_payouts' => '0.00',
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        return response()->json([
            'data' => PayoutResource::collection($payouts),
            'total_historical_payouts' => number_format(
                $this->payoutService->getTotalPayoutsAmount($user->id),
                2
            ),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    public function requestPayout(Request $request)
    {
        $user = auth()->user();


        $validated = $request->validate([
            'wallet_id' => 'nullable|integer|exists:wallets,id',
        ]);

        try {
            $result = $this->payoutService->requestPayout($user, $validated['wallet_id'] ?? null);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'message' => $result['message'],
                'payout' => new PayoutResource($result['payout']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your payout request.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(int $id)
    {
        $user = auth()->user();


        $payout = $this->payoutService->getPayoutForUser($id, $user->id);

        if (!$payout) {
            return response()->json([
                'message' => 'Payout not found.',
            ], 404);
        }

        return response()->json([
            'data' => new PayoutResource($payout),
        ]);
    }
}
