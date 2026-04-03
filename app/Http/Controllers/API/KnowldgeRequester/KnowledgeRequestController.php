<?php

namespace App\Http\Controllers\API\KnowldgeRequester;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateKnowledgeRequest;
use App\Http\Resources\KnowledgeRequestResource;
use App\Models\KnowledgeRequest;
use App\Services\KnowledgeRequestService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KnowledgeRequestController extends Controller
{
    public function __construct(
        protected KnowledgeRequestService $service,
        protected PaymentService $paymentService,
    ) {}

    public function show($id)
{
    try {

        $user = auth()->user();

        $requestModel = KnowledgeRequest::with([
            'media',
            'user'
        ])
        ->where('id', $id)
        ->where('user_id', $user->id)
        ->firstOrFail();

        return response()->json([
            'data' => new KnowledgeRequestResource($requestModel)
        ], 200);

    } catch (\Throwable $e) {

        Log::error('Failed to load KR details', [
            'user_id' => auth()->id(),
            'request_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Request not found.'
        ], 404);
    }
}
    public function store(CreateKnowledgeRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('media')) {
            $files = $request->file('media');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $mime = $file->getMimeType();
                $sizeMB = $file->getSize() / (1024 * 1024);

                if (str_contains($mime, 'image') && $sizeMB > 10) {
                    throw ValidationException::withMessages([
                        'media' => ['Each image must be 10MB or less.']
                    ]);
                }

                if (str_contains($mime, 'video') && $sizeMB > 100) {
                    throw ValidationException::withMessages([
                        'media' => ['Each video must be 100MB or less.']
                    ]);
                }
            }
        }

        $totalBudget = $this->service->calculateBudget(
            $data['pay_per_kp'],
            $data['number_of_kps']
        );

        $result = DB::transaction(function () use ($request, $data, $totalBudget) {
            $requestModel = $this->service->createRequest([
                'user_id' => $request->user()->id,
                'category' => $data['category'],
                'details' => $data['details'],
                'pay_per_kp' => $data['pay_per_kp'],
                'number_of_kps' => $data['number_of_kps'],
                'review_fee' => config('knowledge_request.review_fee', 2),
                'total_budget' => $totalBudget,
                'neighborhood' => $data['neighborhood'],
                'status' => KnowledgeRequest::STATUS_PENDING_PAYMENT,
                'progress' => 0,
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($request->hasFile('media')) {
                $this->service->storeMedia(
                    $requestModel->id,
                    $request->file('media')
                );
            }

            $fees = $this->paymentService->calculateFees($totalBudget);
            $paypalOrder = $this->paymentService->createPayPalOrder($totalBudget, $requestModel->id);

            if (! $paypalOrder['success']) {
                throw new \RuntimeException($paypalOrder['message'] ?? 'PayPal order creation failed.');
            }

            return [
                'requestModel' => $requestModel,
                'fees' => $fees,
                'paypalOrder' => $paypalOrder,
            ];
        });

        return response()->json([
            'message' => 'Request created. Complete payment to submit.',
            'data' => new KnowledgeRequestResource($result['requestModel']->load('media')),
            'payment' => [
                'knowledge_request_id' => $result['requestModel']->id,
                'paypal_order_id' => $result['paypalOrder']['paypal_order_id'],
                'reference_id' => $result['paypalOrder']['reference_id'],
                'fees' => $result['fees'],
                'turnstile_site_key' => config('payment.turnstile.site_key'),
                'paypal_client_id' => config('services.paypal.client_id'),
            ],
        ], 201);
    }

    public function pendingApproval(): \Illuminate\Http\JsonResponse
    {
        try {
            $user = auth()->user();

            $pendingRequests = $this->service->getPendingApprovalRequests($user);

            return response()->json([
                'data' => KnowledgeRequestResource::collection($pendingRequests),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to load KR pending approval requests', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load pending requests. Please try again.',
            ], 500);
        }
    }

    public function index()
    {
        try {
            $user = auth()->user();

            $activeRequests = $this->service->getActiveRequests($user);
            $completedRequests = $this->service->getCompletedRequests($user);
            $pendingRequests=$this->service->getPendingApprovalRequests($user);

            $isEmpty = $activeRequests->isEmpty() && $completedRequests->isEmpty();

            return response()->json([
                'pendingRequests' => KnowledgeRequestResource::collection($pendingRequests),
                'active_requests' => KnowledgeRequestResource::collection($activeRequests),
                'completed_requests' => KnowledgeRequestResource::collection($completedRequests),
                'empty_state' => $isEmpty ? [
                    'title' => 'No requests yet',
                    'description' => 'You can create Survey, Essay, Photo, Video, or Errand requests.',
                    'cta' => 'Create New Request',
                ] : null,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to load KR dashboard', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load your requests. Please try again.',
            ], 500);
        }
    }
}
