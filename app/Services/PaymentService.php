<?php

namespace App\Services;

use App\Models\KnowledgeRequest;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\PaymentRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected PaymentRepository $paymentRepository;

    protected string $clientId;

    protected string $clientSecret;

    protected string $baseUrl;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = config("services.paypal.{$mode}.base_url");
    }

    /**
     * Calculate fees for a given amount.
     *
     * System fee: fixed (e.g. $5.00)
     * Payment fee: percentage of (amount + system fee) + fixed surcharge
     * Total: amount + system fee + payment fee
     */
    public function calculateFees(float $amount): array
    {
        $systemFee = config('payment.system_fee');
        $feePercentage = config('payment.payment_fee_percentage');
        $feeFixed = config('payment.payment_fee_fixed');

        $paymentFee = round(($amount + $systemFee) * $feePercentage + $feeFixed, 2);
        $total = round($amount + $systemFee + $paymentFee, 2);

        return [
            'amount' => number_format($amount, 2, '.', ''),
            'system_fee' => number_format($systemFee, 2, '.', ''),
            'payment_fee' => number_format($paymentFee, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    /**
     * Verify Cloudflare Turnstile CAPTCHA token.
     */
    public function verifyTurnstile(string $token): bool
    {
        $secretKey = config('payment.turnstile.secret_key');
        $verifyUrl = config('payment.turnstile.verify_url');

        try {
            $response = Http::asForm()->post($verifyUrl, [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (Exception $e) {
            Log::error('Turnstile verification failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get a PayPal access token using client credentials.
     */
    protected function getPayPalAccessToken(): ?string
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('PayPal access token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('PayPal access token exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Create a PayPal order for the given amount.
     * The frontend uses this order ID with PayPal JS SDK to render card fields.
     */
    public function createPayPalOrder(float $amount, int $knowledgeRequestId): array
    {
        $fees = $this->calculateFees($amount);
        $total = $fees['total'];

        $accessToken = $this->getPayPalAccessToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable. Please try again later.',
            ];
        }

        try {
            $referenceId = 'PAY-' . strtoupper(Str::random(12));

            $response = Http::withToken($accessToken)
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $referenceId,
                            'description' => "Payment for Knowledge Request #{$knowledgeRequestId}",
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => $total,
                                'breakdown' => [
                                    'item_total' => [
                                        'currency_code' => 'USD',
                                        'value' => $fees['amount'],
                                    ],
                                    'handling' => [
                                        'currency_code' => 'USD',
                                        'value' => number_format((float) $fees['system_fee'] + (float) $fees['payment_fee'], 2, '.', ''),
                                    ],
                                ],
                            ],
                            'items' => [
                                [
                                    'name' => "Knowledge Request #{$knowledgeRequestId}",
                                    'quantity' => '1',
                                    'unit_amount' => [
                                        'currency_code' => 'USD',
                                        'value' => $fees['amount'],
                                    ],
                                    'category' => 'DIGITAL_GOODS',
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('PayPal create order failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Unable to initiate payment. Please try again.',
                ];
            }

            $orderData = $response->json();

            return [
                'success' => true,
                'paypal_order_id' => $orderData['id'],
                'reference_id' => $referenceId,
                'fees' => $fees,
            ];
        } catch (Exception $e) {
            Log::error('PayPal create order exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Capture a PayPal order after the payer approves it.
     * Uses idempotency key to prevent duplicate charges.
     */
    public function capturePayment(
        User $user,
        KnowledgeRequest $knowledgeRequest,
        string $paypalOrderId,
        float $amount,
        string $idempotencyKey
    ): array {

        // log message
        \Log::info('messageed captured testttttttttt');
        // Check for existing payment with same idempotency key
        $existingPayment = $this->paymentRepository->findByIdempotencyKey($idempotencyKey);

        if ($existingPayment) {
            if ($existingPayment->isCompleted()) {
                return [
                    'success' => true,
                    'message' => 'Payment already completed.',
                    'payment' => $existingPayment,
                ];
            }

            if ($existingPayment->status === Payment::STATUS_PROCESSING) {
                return [
                    'success' => false,
                    'message' => 'Payment is currently being processed. Please wait.',
                ];
            }
        }

        \Log::info('messageed captured anaaaaas');
        // Calculate and validate fees
        $fees = $this->calculateFees($amount);

        \Log::info('messageed captured eeeeeeee');

        return DB::transaction(function () use (
            $user,
            $knowledgeRequest,
            $paypalOrderId,
            $idempotencyKey,
            $fees,
            $existingPayment
        ) {
            // Create or reuse payment record
            $payment = $existingPayment ?? $this->paymentRepository->create([
                'user_id' => $user->id,
                'knowledge_request_id' => $knowledgeRequest->id,
                'amount' => $fees['amount'],
                'system_fee' => $fees['system_fee'],
                'payment_fee' => $fees['payment_fee'],
                'total' => $fees['total'],
                'paypal_order_id' => $paypalOrderId,
                'reference_id' => 'PAY-' . strtoupper(Str::random(12)),
                'idempotency_key' => $idempotencyKey,
                'status' => Payment::STATUS_PROCESSING,
                'payer_email' => $user->email,
            ]);

            // Capture the PayPal order
            $captureResult = $this->capturePayPalOrder($paypalOrderId);

            if (! $captureResult['success']) {
                $this->paymentRepository->updateStatus(
                    $payment,
                    Payment::STATUS_FAILED,
                    $captureResult['message']
                );

                return [
                    'success' => false,
                    'message' => 'Payment could not be processed. Please check your payment details and try again.',
                    'payment' => $payment->fresh(),
                ];
            }

            // Update payment with capture details
            $payment->paypal_capture_id = $captureResult['capture_id'] ?? null;
            $payment->payer_email = $captureResult['payer_email'] ?? $payment->payer_email;
            $payment->save();

            $this->paymentRepository->updateStatus($payment, Payment::STATUS_COMPLETED);

            // Transition knowledge request from pending_payment to pending_moderation
            if ($knowledgeRequest->isPendingPayment()) {
                $knowledgeRequest->update(['status' => KnowledgeRequest::STATUS_PENDING_MODERATION]);
            }

            return [
                'success' => true,
                'message' => 'Payment completed successfully.',
                'payment' => $payment->fresh(),
            ];
        });
    }

    /**
     * Capture a PayPal order via the API.
     */
    protected function capturePayPalOrder(string $orderId): array
    {
        $accessToken = $this->getPayPalAccessToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable.',
            ];
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->send('POST', "{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

            if (! $response->successful()) {
                $body = $response->json();
                Log::error('PayPal capture failed', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment could not be processed.',
                ];
            }

            $data = $response->json();
            $captureId = null;
            $payerEmail = $data['payer']['email_address'] ?? null;

            // Extract capture ID from the response
            if (isset($data['purchase_units'][0]['payments']['captures'][0]['id'])) {
                $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'];
            }

            $captureStatus = $data['purchase_units'][0]['payments']['captures'][0]['status'] ?? null;

            if ($captureStatus !== 'COMPLETED') {
                return [
                    'success' => false,
                    'message' => 'Payment was not completed by PayPal.',
                    'capture_id' => $captureId,
                ];
            }

            return [
                'success' => true,
                'capture_id' => $captureId,
                'payer_email' => $payerEmail,
            ];
        } catch (Exception $e) {
            Log::error('PayPal capture exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable.',
            ];
        }
    }

    /**
     * Get payment details for a knowledge request.
     */
    public function getPaymentForRequest(int $knowledgeRequestId): ?Payment
    {
        return Payment::where('knowledge_request_id', $knowledgeRequestId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->first();
    }
}
