<?php

namespace App\Http\Controllers\API\KnowledgeProvider;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\UploadSubmissionMediaRequest;
use App\Http\Resources\KP\TaskPageResource;
use App\Services\TaskPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class TaskPageController extends Controller
{
    protected TaskPageService $taskPageService;

    public function __construct(TaskPageService $taskPageService)
    {
        $this->taskPageService = $taskPageService;
    }

    /**
     * Get task page details for a specific request
     *
     * @param int $requestId
     * @return JsonResponse
     */
    public function show(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $taskData = $this->taskPageService->getTaskPageData($user, $requestId);

            if (!$taskData) {
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
     * Save draft work
     *
     * @param SaveDraftRequest $request
     * @param int $requestId
     * @return JsonResponse
     */
    public function saveDraft(SaveDraftRequest $request, int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->saveDraft(
                $user,
                $requestId,
                $request->input('text_content')
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'submission_id' => $result['submission']->id,
                    'status' => $result['submission']->status,
                ],
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
     *
     * @param UploadSubmissionMediaRequest $request
     * @param int $requestId
     * @return JsonResponse
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
     *
     * @param int $requestId
     * @param int $mediaId
     * @return JsonResponse
     */
    public function removeMedia(int $requestId, int $mediaId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->removeMedia($user, $mediaId);

            if (!$result['success']) {
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
     *
     * @param int $requestId
     * @return JsonResponse
     */
    public function submitWork(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->taskPageService->submitWork($user, $requestId);

            if (!$result['success']) {
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
     *
     * @param int $requestId
     * @return JsonResponse
     */
    public function getStatus(int $requestId): JsonResponse
    {
        try {
            $user = Auth::user();

            $taskData = $this->taskPageService->getTaskPageData($user, $requestId);

            if (!$taskData) {
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
