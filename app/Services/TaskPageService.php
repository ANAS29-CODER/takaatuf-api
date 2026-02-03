<?php

namespace App\Services;

use App\Models\KnowledgeRequest;
use App\Models\User;
use App\Models\UserKnowledgeRequest;
use App\Models\WorkSubmission;
use App\Repositories\EarningRepository;
use App\Repositories\KnowledgeProviderRepository;
use App\Repositories\WorkSubmissionRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskPageService
{
    protected KnowledgeProviderRepository $kpRepo;
    protected WorkSubmissionRepository $submissionRepo;
    protected EarningRepository $earningRepo;

    public function __construct(
        KnowledgeProviderRepository $kpRepo,
        WorkSubmissionRepository $submissionRepo,
        EarningRepository $earningRepo
    ) {
        $this->kpRepo = $kpRepo;
        $this->submissionRepo = $submissionRepo;
        $this->earningRepo = $earningRepo;
    }

    /**
     * Get task page data for a KP
     */
    public function getTaskPageData(User $user, int $requestId): ?array
    {
        if (!$this->kpRepo->isAssignedToRequest($user->id, $requestId)) {
            return null;
        }

        $request = $this->kpRepo->getTaskPageDetails($user->id, $requestId);

        if (!$request) {
            return null;
        }

        // Get or create work submission
        $submission = $this->submissionRepo->getByUserAndRequest($user->id, $requestId);

        return [
            'request' => $request,
            'submission' => $submission,
            'can_edit' => $this->canEditSubmission($user->id, $requestId, $submission),
            'is_read_only' => $this->isReadOnly($submission),
            'status_info' => $this->getStatusInfo($request->kp_status, $submission),
        ];
    }

    /**
     * Check if submission can be edited
     */
    public function canEditSubmission(int $userId, int $requestId, ?WorkSubmission $submission): bool
    {
        // Check assignment status first
        if (!$this->kpRepo->canEditAssignment($userId, $requestId)) {
            return false;
        }

        // If no submission yet, can edit
        if (!$submission) {
            return true;
        }

        return $submission->canBeEdited();
    }

    /**
     * Check if task is read-only
     */
    public function isReadOnly(?WorkSubmission $submission): bool
    {
        if (!$submission) {
            return false;
        }

        return $submission->isReadOnly();
    }

    /**
     * Get status info for display
     */
    public function getStatusInfo(string $assignmentStatus, ?WorkSubmission $submission): array
    {
        $statusLabel = $this->kpRepo->getStatusLabel($assignmentStatus);

        $info = [
            'assignment_status' => $assignmentStatus,
            'assignment_status_label' => $statusLabel,
            'submission_status' => $submission?->status,
            'submission_status_label' => $submission ? $this->getSubmissionStatusLabel($submission->status) : null,
            'rejection_reason' => $submission?->rejection_reason,
            'can_resubmit' => $submission?->isRejected() ?? false,
        ];

        return $info;
    }

    /**
     * Get submission status label
     */
    protected function getSubmissionStatusLabel(string $status): string
    {
        return match ($status) {
            WorkSubmission::STATUS_DRAFT => 'Draft',
            WorkSubmission::STATUS_SUBMITTED => 'Submitted',
            WorkSubmission::STATUS_APPROVED => 'Approved',
            WorkSubmission::STATUS_REJECTED => 'Rejected',
            default => ucfirst($status),
        };
    }

    /**
     * Save draft work
     */
    public function saveDraft(User $user, int $requestId, ?string $textContent): array
    {
        $submission = $this->submissionRepo->getByUserAndRequest($user->id, $requestId);

        if (!$this->canEditSubmission($user->id, $requestId, $submission)) {
            return [
                'success' => false,
                'message' => 'You cannot edit this submission.',
            ];
        }

        try {
            $submission = $this->submissionRepo->saveDraft($user->id, $requestId, $textContent);

            // Update assignment status to in_progress if pending
            $assignment = $this->kpRepo->getAssignment($user->id, $requestId);
            if ($assignment && $assignment->status === UserKnowledgeRequest::STATUS_PENDING) {
                $this->kpRepo->updateAssignmentStatus($user->id, $requestId, UserKnowledgeRequest::STATUS_IN_PROGRESS);
            }

            return [
                'success' => true,
                'message' => 'Draft saved successfully.',
                'submission' => $submission,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload media files
     */
    public function uploadMedia(User $user, int $requestId, array $files): array
    {
        $submission = $this->submissionRepo->getByUserAndRequest($user->id, $requestId);

        if (!$this->canEditSubmission($user->id, $requestId, $submission)) {
            return [
                'success' => false,
                'message' => 'You cannot upload files to this submission.',
            ];
        }

        // Get or create submission
        $submission = $this->submissionRepo->getOrCreate($user->id, $requestId);

        $uploadedMedia = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $mediaData = $this->processUploadedFile($file, $submission->id);
                $uploadedMedia[] = $mediaData;
            } catch (\Exception $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        // Update assignment status to in_progress if pending
        $assignment = $this->kpRepo->getAssignment($user->id, $requestId);
        if ($assignment && $assignment->status === UserKnowledgeRequest::STATUS_PENDING) {
            $this->kpRepo->updateAssignmentStatus($user->id, $requestId, UserKnowledgeRequest::STATUS_IN_PROGRESS);
        }

        return [
            'success' => count($errors) === 0,
            'message' => count($errors) === 0 ? 'Files uploaded successfully.' : 'Some files failed to upload.',
            'uploaded' => $uploadedMedia,
            'errors' => $errors,
        ];
    }

    /**
     * Process uploaded file
     */
    protected function processUploadedFile(UploadedFile $file, int $submissionId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $type = $this->getFileType($extension);

        // Store file
        $path = $file->store('work_submissions/' . $submissionId, 'public');

        // Create media record
        $media = $this->submissionRepo->addMedia(
            $submissionId,
            $path,
            $file->getClientOriginalName(),
            $type,
            $file->getSize()
        );

        return [
            'id' => $media->id,
            'original_name' => $media->original_name,
            'type' => $media->type,
            'url' => Storage::url($path),
            'file_size' => $media->formatted_file_size,
        ];
    }

    /**
     * Get file type from extension
     */
    protected function getFileType(string $extension): string
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        }

        if (in_array($extension, $videoExtensions)) {
            return 'video';
        }

        return 'document';
    }

    /**
     * Remove media file
     */
    public function removeMedia(User $user, int $mediaId): array
    {
        $removed = $this->submissionRepo->removeMedia($mediaId, $user->id);

        return [
            'success' => $removed,
            'message' => $removed ? 'File removed successfully.' : 'Failed to remove file.',
        ];
    }

    /**
     * Submit work for review
     */
    public function submitWork(User $user, int $requestId): array
    {
        $submission = $this->submissionRepo->getByUserAndRequest($user->id, $requestId);

        if (!$submission) {
            return [
                'success' => false,
                'message' => 'No work found to submit. Please save your work first.',
                'errors' => [],
            ];
        }

        if (!$this->canEditSubmission($user->id, $requestId, $submission)) {
            return [
                'success' => false,
                'message' => 'You cannot submit this work.',
                'errors' => [],
            ];
        }

        // Get request category for validation
        $request = KnowledgeRequest::find($requestId);
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Request not found.',
                'errors' => [],
            ];
        }

        // Validate required content
        $validationErrors = $this->submissionRepo->hasRequiredContent($submission, $request->category);
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'message' => 'Please complete all required fields before submitting.',
                'errors' => $validationErrors,
            ];
        }

        try {
            DB::beginTransaction();

            // Submit the work
            $submission = $this->submissionRepo->submitWork($user->id, $requestId);

            // Update assignment status and progress
            $this->kpRepo->updateProgress($user->id, $requestId, 100, UserKnowledgeRequest::STATUS_AWAITING_REVIEW);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Work submitted successfully. It is now pending review.',
                'submission' => $submission,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to submit work. Please try again.',
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Approve submission (for admin/KR use)
     */
    public function approveSubmission(int $submissionId, int $requestId, int $userId): array
    {
        try {
            DB::beginTransaction();

            $submission = $this->submissionRepo->approveSubmission($submissionId);

            // Update assignment status
            $this->kpRepo->updateAssignmentStatus($userId, $requestId, UserKnowledgeRequest::STATUS_APPROVED);

            // Create earning record
            $assignment = $this->kpRepo->getAssignment($userId, $requestId);
            if ($assignment) {
                $this->earningRepo->create(
                    $userId,
                    $assignment->payout_amount,
                    'Payment for completed request #' . $requestId
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Submission approved successfully.',
                'submission' => $submission,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to approve submission.',
            ];
        }
    }

    /**
     * Reject submission (for admin/KR use)
     */
    public function rejectSubmission(int $submissionId, int $requestId, int $userId, ?string $reason = null): array
    {
        try {
            DB::beginTransaction();

            $submission = $this->submissionRepo->rejectSubmission($submissionId, $reason);

            // Update assignment status
            $this->kpRepo->updateAssignmentStatus($userId, $requestId, UserKnowledgeRequest::STATUS_REJECTED);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Submission rejected.',
                'submission' => $submission,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to reject submission.',
            ];
        }
    }
}
