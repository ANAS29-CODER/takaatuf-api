<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get all wallets for the authenticated user
     */
    public function index()
    {
        $user = auth()->user();

        $wallets = $this->walletService->getUserWallets($user->id);

        return response()->json([
            'data' => WalletResource::collection($wallets),
        ]);
    }


    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'wallet_type' => ['required', Rule::in(Wallet::WALLET_TYPES)],
            'wallet_address' => 'required|string',
            'is_primary' => 'boolean',
        ]);

        try {
            $result = $this->walletService->addWallet(
                $user->id,
                $validated['wallet_type'],
                $validated['wallet_address'],
                $validated['is_primary'] ?? false
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'message' => $result['message'],
                'data' => new WalletResource($result['wallet']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while adding your wallet.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get a single wallet
     */
    public function show(int $id)
    {
        $user = auth()->user();


        $wallet = $this->walletService->getWalletForUser($user->id, $id);

        if (!$wallet) {
            return response()->json([
                'message' => 'Wallet not found.',
            ], 404);
        }

        return response()->json([
            'data' => new WalletResource($wallet),
        ]);
    }

    /**
     * Update a wallet
     */
    public function update(Request $request, int $id)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'wallet_type' => ['required', Rule::in(Wallet::WALLET_TYPES)],
            'wallet_address' => 'required|string',
        ]);

        try {
            $result = $this->walletService->updateWallet(
                $user->id,
                $id,
                $validated['wallet_type'],
                $validated['wallet_address']
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], $result['message'] === 'Wallet not found.' ? 404 : 422);
            }

            return response()->json([
                'message' => $result['message'],
                'data' => new WalletResource($result['wallet']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while updating your wallet.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $user = auth()->user();

        try {
            $result = $this->walletService->deleteWallet($user->id, $id);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while deleting your wallet.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Set a wallet as primary
     */
    public function setPrimary(int $id)
    {
        $user = auth()->user();

        try {
            $result = $this->walletService->setPrimaryWallet($user->id, $id);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'message' => $result['message'],
                'data' => new WalletResource($result['wallet']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while setting primary wallet.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
