<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminAuditLogResource;
use App\Http\Resources\Admin\AdminBudgetHistoryResource;
use App\Http\Resources\Admin\AdminDashboardResource;
use App\Http\Resources\Admin\AdminKnowledgeRequestResource;
use App\Http\Resources\Admin\AdminKPApplicationResource;
use App\Http\Resources\Admin\AdminPayoutResource;
use App\Http\Resources\Admin\AdminWorkSubmissionResource;
use App\Models\BudgetHistory;
use App\Models\KnowledgeRequest;
use App\Models\Payout;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Get dashboard overview statistics
     */
    public function dashboard(): JsonResponse
    {
        $stats = $this->adminService->getDashboardStats();

        return response()->json([
            'data' => new AdminDashboardResource($stats),
        ]);
    }

    // ==================== KNOWLEDGE REQUEST MODERATION ====================

    /**
     * Get pending knowledge requests for moderation
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $requests = $this->adminService->getPendingRequests($perPage);

        return response()->json([
            'data' => AdminKnowledgeRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get all knowledge requests with filters
     */
    public function allRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category', 'user_id', 'search']);
        $perPage = $request->input('per_page', 15);

        $requests = $this->adminService->getRequests($filters, $perPage);

        return response()->json([
            'data' => AdminKnowledgeRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get knowledge request details
     */
    public function showRequest(int $id): JsonResponse
    {
        $request = $this->adminService->getRequestDetails($id);

        if (!$request) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        return response()->json([
            'data' => new AdminKnowledgeRequestResource($request),
        ]);
    }

    /**
     * Approve a knowledge request
     */
    public function approveRequest(int $id, Request $request): JsonResponse
    {
        $admin = auth()->user();
        $result = $this->adminService->approveRequest($id, $admin, $request);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminKnowledgeRequestResource($result['request']),
        ]);
    }

    /**
     * Reject a knowledge request
     */
    public function rejectRequest(int $id, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->rejectRequest($id, $admin, $validated['reason'] ?? null, $httpRequest);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminKnowledgeRequestResource($result['request']),
        ]);
    }

    // ==================== KP APPLICATION MANAGEMENT ====================

    /**
     * Get KP applications for a specific request
     */
    public function getKPApplicationsForRequest(int $requestId): JsonResponse
    {
        $request = $this->adminService->getRequestDetails($requestId);

        if (!$request) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $applications = $this->adminService->getKPApplications($requestId);

        return response()->json([
            'data' => AdminKPApplicationResource::collection($applications),
            'request' => new AdminKnowledgeRequestResource($request),
        ]);
    }

    /**
     * Get all pending KP applications
     */
    public function pendingKPApplications(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $applications = $this->adminService->getPendingKPApplications($perPage);

        return response()->json([
            'data' => AdminKPApplicationResource::collection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Approve a KP application
     */
    public function approveKPApplication(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'user_id' => 'required|integer|exists:users,id',
            'request_id' => 'required|integer|exists:knowledge_requests,id',
        ]);

     
        $admin = auth()->user();
        $result = $this->adminService->approveKPApplication(
            $validated['user_id'],
            $validated['request_id'],
            $admin,
            $httpRequest
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminKPApplicationResource($result['assignment']),
        ]);
    }

    /**
     * Reject a KP application
     */
    public function rejectKPApplication(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'user_id' => 'required|integer|exists:users,id',
            'request_id' => 'required|integer|exists:knowledge_requests,id',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->rejectKPApplication(
            $validated['user_id'],
            $validated['request_id'],
            $admin,
            $httpRequest
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    // ==================== BUDGET MANAGEMENT ====================

    /**
     * Update budget for a knowledge request
     */
    public function updateBudget(int $requestId, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'new_budget' => 'required|numeric|min:0',
            'new_pay_per_kp' => 'nullable|numeric|min:0',
            'change_type' => 'required|in:' . implode(',', BudgetHistory::getChangeTypes()),
            'reason' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->updateBudget(
            $requestId,
            $validated['new_budget'],
            $validated['new_pay_per_kp'] ?? null,
            $validated['change_type'],
            $admin,
            $validated['reason'] ?? null,
            $httpRequest
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminBudgetHistoryResource($result['history']),
        ]);
    }

    /**
     * Get budget history for a knowledge request
     */
    public function getBudgetHistory(int $requestId): JsonResponse
    {
        $request = $this->adminService->getRequestDetails($requestId);

        if (!$request) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $history = $this->adminService->getBudgetHistory($requestId);

        return response()->json([
            'data' => AdminBudgetHistoryResource::collection($history),
            'request' => new AdminKnowledgeRequestResource($request),
        ]);
    }

    // ==================== PAYOUT MANAGEMENT ====================

    /**
     * Get pending payouts
     */
    public function pendingPayouts(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $payouts = $this->adminService->getPendingPayouts($perPage);

        return response()->json([
            'data' => AdminPayoutResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Get all payouts with filters
     */
    public function allPayouts(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'user_id']);
        $perPage = $request->input('per_page', 15);

        $payouts = $this->adminService->getPayouts($filters, $perPage);

        return response()->json([
            'data' => AdminPayoutResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Get payout details
     */
    public function showPayout(int $id): JsonResponse
    {
        $payout = $this->adminService->getPayoutDetails($id);

        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }

        return response()->json([
            'data' => new AdminPayoutResource($payout),
        ]);
    }

    /**
     * Complete a payout with transaction ID
     */
    public function completePayout(int $id, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'transaction_id' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->completePayout(
            $id,
            $validated['transaction_id'],
            $admin,
            $validated['notes'] ?? null,
            $httpRequest
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminPayoutResource($result['payout']),
        ]);
    }

    /**
     * Fail/Cancel a payout
     */
    public function failPayout(int $id, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->failPayout(
            $id,
            $admin,
            $validated['notes'] ?? null,
            $httpRequest
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new AdminPayoutResource($result['payout']),
        ]);
    }

    // ==================== WORK SUBMISSION MANAGEMENT ====================

    /**
     * Get pending work submissions
     */
    public function pendingSubmissions(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $submissions = $this->adminService->getPendingSubmissions($perPage);

        return response()->json([
            'data' => AdminWorkSubmissionResource::collection($submissions),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    /**
     * Get work submission details
     */
    public function showSubmission(int $id): JsonResponse
    {
        $submission = $this->adminService->getSubmissionDetails($id);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        return response()->json([
            'data' => new AdminWorkSubmissionResource($submission),
        ]);
    }

    /**
     * Approve a work submission
     */
    public function approveSubmission(int $id, Request $request): JsonResponse
    {
        $admin = auth()->user();
        $result = $this->adminService->approveSubmission($id, $admin, $request);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    /**
     * Reject a work submission
     */
    public function rejectSubmission(int $id, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        $result = $this->adminService->rejectSubmission($id, $admin, $validated['reason'] ?? null, $httpRequest);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    // ==================== AUDIT LOGS ====================

    /**
     * Get audit logs with filters
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['action', 'model_type', 'model_id', 'user_id', 'from_date', 'to_date']);
        $perPage = $request->input('per_page', 15);

        $logs = $this->adminService->getAuditLogs($filters, $perPage);

        return response()->json([
            'data' => AdminAuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
