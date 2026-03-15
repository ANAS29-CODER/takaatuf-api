<?php

namespace App\Http\Controllers\API\KnowledgeProvider;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitWorkRequest;
use App\Http\Requests\UploadSubmissionMediaRequest;
use App\Http\Resources\KP\RequestDetailResource;
use App\Http\Resources\KP\TaskPageResource;
use App\Models\KnowledgeRequest;
use App\Services\TaskPageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskPageController extends Controller
{
    protected TaskPageService $taskPageService;

    public function __construct(TaskPageService $taskPageService)
    {
        $this->taskPageService = $taskPageService;
    }

    /**
     * Get task page details for a specific request
     */
    public function show(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $taskData = $this->taskPageService->getTaskPageData($user, $requestId);

            if (! $taskData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not assigned to this request.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => new TaskPageResource(
                    $taskData['request'],
                    $taskData['submission'],
                    $taskData['can_edit'],
                    $taskData['is_read_only'],
                    $taskData['status_info']
                ),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load task page. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get details of any knowledge request by ID
     */
    public function requestDetails(int $requestId): JsonResponse
    {
        try {
            $knowledgeRequest = KnowledgeRequest::with(['media', 'creator'])->find($requestId);

            if (! $knowledgeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new RequestDetailResource($knowledgeRequest),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load request details. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Save draft work
     */
    public function saveDraft(SaveDraftRequest $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->saveDraft(
                $user,
                $requestId,
                $request->input('text_content'),
                $request->file('files')
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            $responseData = [
                'submission_id' => $result['submission']->id,
                'status' => $result['submission']->status,
            ];

            if (! empty($result['uploaded_media'])) {
                $responseData['uploaded_media'] = $result['uploaded_media'];
            }

            if (! empty($result['upload_errors'])) {
                $responseData['upload_errors'] = $result['upload_errors'];
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $responseData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Upload media files
     */
    public function uploadMedia(UploadSubmissionMediaRequest $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->uploadMedia(
                $user,
                $requestId,
                $request->file('files')
            );

            $statusCode = $result['success'] ? 200 : (count($result['errors']) === count($request->file('files')) ? 400 : 207);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'uploaded' => $result['uploaded'],
                    'errors' => $result['errors'],
                ],
            ], $statusCode);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Remove a media file
     */
    public function removeMedia(int $requestId, int $mediaId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->removeMedia($user, $mediaId);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove file. Please try again.',
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Submit work for review
     */
    public function submitWork(SubmitWorkRequest $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->submitWork(
                $user,
                $requestId,
                $request->input('text_content'),
                $request->file('files')
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? [],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'submission_id' => $result['submission']->id,
                    'status' => $result['submission']->status,
                    'submitted_at' => $result['submission']->submitted_at->toDateTimeString(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit work. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'retry' => true,
            ], 500);
        }
    }

    /**
     * Get submission status
     */
    public function getStatus(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $taskData = $this->taskPageService->getTaskPageData($user, $requestId);

            if (! $taskData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not assigned to this request.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $taskData['request']->kp_progress ?? 0,
                    'status' => $taskData['status_info'],
                    'can_edit' => $taskData['can_edit'],
                    'is_read_only' => $taskData['is_read_only'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status. Please try again.',
                'retry' => true,
            ], 500);
        }
    }
}
