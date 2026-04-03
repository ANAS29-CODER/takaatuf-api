<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateFeesRequest;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\KnowledgeRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * GET /payment/{request_id}
     * Show the payment page data for a knowledge request.
     */
    public function show(int $requestId): JsonResponse
    {
        $knowledgeRequest = KnowledgeRequest::findOrFail($requestId);
        $user = auth()->user();

        if ($knowledgeRequest->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to pay for this request.',
            ], 403);
        }

        // Check if already paid
        $existingPayment = $this->paymentService->getPaymentForRequest($knowledgeRequest->id);

        if ($existingPayment) {
            return response()->json([
                'message' => 'This request has already been paid.',
                'data' => new PaymentResource($existingPayment),
            ]);
        }

        return response()->json([
            'data' => [
                'knowledge_request_id' => $knowledgeRequest->id,
                'category' => $knowledgeRequest->category,
                'details' => $knowledgeRequest->details,
                'pay_per_kp' => $knowledgeRequest->pay_per_kp,
                'number_of_kps' => $knowledgeRequest->number_of_kps,
                'turnstile_site_key' => config('payment.turnstile.site_key'),
                'paypal_client_id' => config('services.paypal.client_id'),
            ],
        ]);
    }

    /**
     * POST /payment/{request_id}/calculate-fees
     * Calculate fees for a given amount (called on amount input change).
     */
    public function calculateFees(CalculateFeesRequest $request, int $requestId): JsonResponse
    {
        $fees = $this->paymentService->calculateFees((float) $request->validated()['amount']);

        return response()->json([
            'data' => $fees,
        ]);
    }

    /**
     * POST /payment/{request_id}/create-order
     * Create a PayPal order so the frontend can render card fields.
     */
    public function createOrder(CalculateFeesRequest $request, int $requestId): JsonResponse
    {
        $knowledgeRequest = KnowledgeRequest::findOrFail($requestId);
        $user = auth()->user();

        if ($knowledgeRequest->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to pay for this request.',
            ], 403);
        }

        // Check if already paid
        if ($this->paymentService->getPaymentForRequest($knowledgeRequest->id)) {
            return response()->json([
                'message' => 'This request has already been paid.',
            ], 409);
        }

        $amount = (float) $request->validated()['amount'];
        $result = $this->paymentService->createPayPalOrder($amount, $knowledgeRequest->id);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 502);
        }

        return response()->json([
            'data' => [
                'paypal_order_id' => $result['paypal_order_id'],
                'reference_id' => $result['reference_id'],
                'fees' => $result['fees'],
            ],
        ]);
    }

    /**
     * POST /payment/{request_id}/capture
     * Capture the payment after the payer completes the card form.
     */
    public function capture(ProcessPaymentRequest $request, int $requestId): JsonResponse
    {
        \Log::info('Processing payment capture', [
            'request_id' => $requestId,
            'user_id' => auth()->id(),
            'payload' => $request->validated(),
        ]);
        $knowledgeRequest = KnowledgeRequest::findOrFail($requestId);
        $user = auth()->user();

        if ($knowledgeRequest->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to pay for this request.',
            ], 403);
        }

        // Verify Turnstile CAPTCHA
        if (! $this->paymentService->verifyTurnstile($request->validated()['turnstile_token'])) {
            return response()->json([
                'message' => 'CAPTCHA verification failed. Please try again.',
            ], 422);
        }

        $validated = $request->validated();

        \Log::info('Turnstile verification passed, proceeding with payment capture', [
            'request_id' => $requestId,
            'user_id' => auth()->id(),
        ]);
        $result = $this->paymentService->capturePayment(
            $user,
            $knowledgeRequest,
            $validated['paypal_order_id'],
            (float) $validated['amount'],
            $validated['idempotency_key']
        );

        if (! $result['success']) {
            $statusCode = str_contains($result['message'], 'currently being processed') ? 409 : 422;

            return response()->json([
                'message' => $result['message'],
            ], $statusCode);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new PaymentResource($result['payment']),
        ]);
    }
}
