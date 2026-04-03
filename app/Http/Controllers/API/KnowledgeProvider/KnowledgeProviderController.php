<?php

namespace App\Http\Controllers\API\KnowledgeProvider;

use App\Http\Controllers\Controller;
use App\Http\Resources\KP\ActiveRequestResource;
use App\Http\Resources\KP\AvailableRequestResource;
use App\Http\Resources\KP\CompletedRequestResource;
use App\Http\Resources\KP\KPDashboardResource;
use App\Models\KnowledgeRequest;
use App\Repositories\KnowledgeProviderRepository;
use App\Services\KnowledgeProviderDashboardService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KnowledgeProviderController extends Controller
{
    protected KnowledgeProviderDashboardService $dashboardService;
    protected KnowledgeProviderRepository $kpRepo;

    public function __construct(
        KnowledgeProviderDashboardService $dashboardService,
        KnowledgeProviderRepository $kpRepo
    ) {
        $this->dashboardService = $dashboardService;
        $this->kpRepo = $kpRepo;
    }

    /**
     * Get the Knowledge Provider dashboard
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();

            $dashboardData = $this->dashboardService->getDashboardData($user);

            return response()->json([
                'success' => true,
                'data' => new KPDashboardResource($dashboardData),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get only earnings summary
     */
    public function earningsSummary(): JsonResponse
    {
        try {
            $user = Auth::user();
            $earnings = $this->dashboardService->getEarningsSummary($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'amount' => $earnings['formatted'],
                    'amount_raw' => $earnings['amount'],
                    'currency' => $earnings['currency'],
                    'description' => 'Total earned since last payout',
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load earnings summary.',
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get only active requests
     */
    public function activeRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            $requests = $this->dashboardService->getActiveRequests($user);

            if ($requests->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'count' => 0,
                        'items' => [],
                        'empty_message' => 'You are not currently working on any requests.',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $requests->count(),
                    'items' => ActiveRequestResource::collection($requests),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load active requests.',
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get only available requests
     */
    public function availableRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            $requests = $this->dashboardService->getAvailableRequests($user);

            if ($requests->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'count' => 0,
                        'items' => [],
                        'empty_message' => 'No new requests are currently available.',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $requests->count(),
                    'items' => AvailableRequestResource::collection($requests),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load available requests.',
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get only completed requests
     */
    public function completedRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            $requests = $this->dashboardService->getCompletedRequests($user);

            if ($requests->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_count' => 0,
                        'items' => [],
                        'empty_message' => 'No completed requests yet.',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_count' => $requests->count(),
                    'items' => CompletedRequestResource::collection($requests),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load completed requests.',
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Apply to a knowledge request
     */
    public function applyToRequest(Request $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $knowledgeRequest = KnowledgeRequest::find($requestId);

            if (!$knowledgeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found.',
                ], 404);
            }

            if ($knowledgeRequest->status !== KnowledgeRequest::STATUS_AVAILABLE) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is no longer available.',
                ], 400);
            }

            if ($this->kpRepo->isAssignedToRequest($user->id, $requestId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already applied to this request.',
                ], 400);
            }

            $payoutAmount = $knowledgeRequest->pay_per_kp;
            $applied = $this->kpRepo->applyToRequest($user->id, $requestId, $payoutAmount);

            if (!$applied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to apply to request.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully applied to the request.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply to request. Please try again.',
            ], 500);
        }
    }

    /**
     * Update progress on an assigned request
     */
    public function updateProgress(Request $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'progress' => 'required|integer|min:0|max:100',
            ]);

            if (!$this->kpRepo->isAssignedToRequest($user->id, $requestId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this request.',
                ], 403);
            }

            $updated = $this->kpRepo->updateProgress($user->id, $requestId, $validated['progress']);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update progress.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully.',
                'data' => [
                    'progress' => $validated['progress'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress. Please try again.',
            ], 500);
        }
    }

    /**
     * Get details of a specific request
     */
    public function showRequest(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $knowledgeRequest = KnowledgeRequest::with('media')->find($requestId);

            if (!$knowledgeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found.',
                ], 404);
            }

            $assignment = $this->kpRepo->getAssignment($user->id, $requestId);

            $data = [
                'id' => $knowledgeRequest->id,
                'category' => $knowledgeRequest->category,
                'details' => $knowledgeRequest->details,
                'neighborhood' => $knowledgeRequest->neighborhood,
                'payout_amount' => number_format((float) ($assignment->payout_amount ?? $knowledgeRequest->pay_per_kp), 2),
                'due_date' => $knowledgeRequest->due_date?->format('Y-m-d'),
                'status' => $knowledgeRequest->status,
                'media' => $knowledgeRequest->media->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'type' => $m->type,
                        'url' => asset('storage/'.$m->file_path),
                    ];
                }),
                'created_at' => $knowledgeRequest->created_at->toDateTimeString(),
            ];

            if ($assignment) {
                $data['assignment'] = [
                    'status' => $assignment->status,
                    'progress' => $assignment->progress,
                    'payout_amount' => number_format((float) $assignment->payout_amount, 2),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load request details.',
            ], 500);
        }
    }
}
