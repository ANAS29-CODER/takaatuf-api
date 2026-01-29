<?php

namespace App\Services;

use App\Models\PaypalAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayPalService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;
    protected string $webUrl;
    protected string $mode;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = config("services.paypal.{$this->mode}.base_url");
        $this->webUrl = config("services.paypal.{$this->mode}.web_url");
    }

    /**
     * Generate the PayPal OAuth authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'redirect_uri' => config('services.paypal.redirect'),
            'state' => $state,
        ]);

        return "{$this->webUrl}/signin/authorize?{$params}";
    }


    public function getAccessToken(string $authorizationCode): array
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    //'grant_type' => 'authorization_code',
                    'grant_type' => 'client_credentials',
                    'content_type' => 'application/x-www-form-urlencoded',
                    'code' => $authorizationCode,
                    'redirect_uri' => config('services.paypal.redirect'),
                ]);

            if (!$response->successful()) {
                Log::info('the response: ' . $response->body());
                Log::error('PayPal token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with PayPal. Please try again.',
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => $data['expires_in'] ?? 3600,
            ];
        } catch (Exception $e) {
            Log::error('PayPal token exchange exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'PayPal service is temporarily unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                Log::error('PayPal token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to refresh PayPal connection. Please reconnect your account.',
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in' => $data['expires_in'] ?? 3600,
            ];
        } catch (Exception $e) {
            Log::error('PayPal token refresh exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'PayPal service is temporarily unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Get user info from PayPal using access token
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/v1/identity/oauth2/userinfo", [
                    'schema' => 'paypalv1.1',
                ]);

            if (!$response->successful()) {
                Log::error('PayPal user info fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to retrieve PayPal account information.',
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'user_id' => $data['user_id'] ?? $data['payer_id'] ?? null,
                'email' => $data['emails'][0]['value'] ?? null,
                'name' => $data['name'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('PayPal user info exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'PayPal service is temporarily unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Connect PayPal account for a user
     */
    public function connectAccount(User $user, string $authorizationCode): array
    {
        // Exchange code for tokens
        $tokenResult = $this->getAccessToken($authorizationCode);
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        // Get user info from PayPal
        $userInfo = $this->getUserInfo($tokenResult['access_token']);
        if (!$userInfo['success']) {
            return $userInfo;
        }

        // Calculate token expiration
        $expiresAt = now()->addSeconds($tokenResult['expires_in']);

        // Update or create PayPal account record
        $paypalAccount = PaypalAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
                'paypal_account_id' => $userInfo['user_id'],
                'paypal_email' => $userInfo['email'],
                'access_token' => $tokenResult['access_token'],
                'refresh_token' => $tokenResult['refresh_token'],
                'token_expires_at' => $expiresAt,
                'status' => PaypalAccount::STATUS_CONNECTED,
            ]
        );

        return [
            'success' => true,
            'message' => 'PayPal account connected successfully.',
            'paypal_account' => $paypalAccount,
        ];
    }

    /**
     * Disconnect PayPal account for a user
     */
    public function disconnectAccount(User $user): array
    {
        $paypalAccount = $user->paypalAccount;

        if (!$paypalAccount) {
            return [
                'success' => false,
                'message' => 'No PayPal account connected.',
            ];
        }

        $paypalAccount->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'paypal_account_id' => null,
            'status' => PaypalAccount::STATUS_NOT_CONNECTED,
        ]);

        return [
            'success' => true,
            'message' => 'PayPal account disconnected successfully.',
        ];
    }

    /**
     * Update PayPal email manually (without OAuth)
     */
    public function updatePayPalEmail(User $user, string $email): array
    {
        $paypalAccount = PaypalAccount::updateOrCreate(
            ['user_id' => $user->id],
            ['paypal_email' => $email]
        );

        return [
            'success' => true,
            'message' => 'PayPal email updated successfully.',
            'paypal_account' => $paypalAccount,
        ];
    }

    /**
     * Get PayPal account status for a user
     */
    public function getAccountStatus(User $user): array
    {
        $paypalAccount = $user->paypalAccount;

        if (!$paypalAccount) {
            return [
                'status' => PaypalAccount::STATUS_NOT_CONNECTED,
                'paypal_email' => null,
                'is_authenticated' => false,
                'email_mismatch' => false,
            ];
        }

        $isAuthenticated = $paypalAccount->isConnected() && $paypalAccount->paypal_account_id;

        // Check if manually entered email differs from authenticated email
        $emailMismatch = false;
        if ($isAuthenticated && $paypalAccount->paypal_email) {
            // If user had a manual email before authenticating, check for mismatch
            // This would require storing the manual email separately or tracking changes
        }

        return [
            'status' => $paypalAccount->status,
            'paypal_email' => $paypalAccount->paypal_email,
            'is_authenticated' => $isAuthenticated,
            'email_mismatch' => $emailMismatch,
        ];
    }

    public function ensureValidToken(PaypalAccount $paypalAccount): array
    {
        if (!$paypalAccount->isTokenExpired()) {
            return [
                'success' => true,
                'access_token' => $paypalAccount->access_token,
            ];
        }

        if (!$paypalAccount->refresh_token) {
            $paypalAccount->update(['status' => PaypalAccount::STATUS_FAILED]);
            return [
                'success' => false,
                'message' => 'PayPal session expired. Please reconnect your account.',
            ];
        }

        $refreshResult = $this->refreshAccessToken($paypalAccount->refresh_token);

        if (!$refreshResult['success']) {
            $paypalAccount->update(['status' => PaypalAccount::STATUS_FAILED]);
            return $refreshResult;
        }

        $paypalAccount->update([
            'access_token' => $refreshResult['access_token'],
            'refresh_token' => $refreshResult['refresh_token'],
            'token_expires_at' => now()->addSeconds($refreshResult['expires_in']),
            'status' => PaypalAccount::STATUS_CONNECTED,
        ]);

        return [
            'success' => true,
            'access_token' => $refreshResult['access_token'],
        ];
    }


    public function generateState(int $userId): string
    {
        return base64_encode(json_encode([
            'user_id' => $userId,
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ]));
    }

    /**
     * Validate and decode state parameter
     */
    public function validateState(string $state): ?array
    {
        try {
            $decoded = json_decode(base64_decode($state), true);

            if (!$decoded || !isset($decoded['user_id'], $decoded['timestamp'])) {
                return null;
            }

            if (time() - $decoded['timestamp'] > 600) {
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
