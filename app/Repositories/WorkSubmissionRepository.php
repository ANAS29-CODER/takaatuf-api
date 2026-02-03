<?php

namespace App\Repositories;

use App\Models\WorkSubmission;
use App\Models\WorkSubmissionMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkSubmissionRepository
{
    /**
     * Get or create a work submission for a KP and request
     */
    public function getOrCreate(int $userId, int $requestId): WorkSubmission
    {
        return WorkSubmission::firstOrCreate(
            [
                'user_id' => $userId,
                'knowledge_request_id' => $requestId,
            ],
            [
                'status' => WorkSubmission::STATUS_DRAFT,
            ]
        );
    }

    /**
     * Get submission by user and request
     */
    public function getByUserAndRequest(int $userId, int $requestId): ?WorkSubmission
    {
        return WorkSubmission::where('user_id', $userId)
            ->where('knowledge_request_id', $requestId)
            ->with('media')
            ->first();
    }

    /**
     * Save draft (text content and/or media)
     */
    public function saveDraft(int $userId, int $requestId, ?string $textContent = null): WorkSubmission
    {
        $submission = $this->getOrCreate($userId, $requestId);

        if (!$submission->canBeEdited()) {
            throw new \Exception('Submission cannot be edited in current status.');
        }

        $submission->update([
            'text_content' => $textContent,
            'status' => WorkSubmission::STATUS_DRAFT,
        ]);

        return $submission->fresh(['media']);
    }

    /**
     * Submit work for review
     */
    public function submitWork(int $userId, int $requestId): WorkSubmission
    {
        $submission = $this->getByUserAndRequest($userId, $requestId);

        if (!$submission) {
            throw new \Exception('No submission found to submit.');
        }

        if (!$submission->canBeEdited()) {
            throw new \Exception('Submission cannot be submitted in current status.');
        }

        $submission->update([
            'status' => WorkSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $submission->fresh(['media']);
    }

    /**
     * Add media file to submission
     */
    public function addMedia(int $submissionId, string $filePath, string $originalName, string $type, int $fileSize): WorkSubmissionMedia
    {
        return WorkSubmissionMedia::create([
            'work_submission_id' => $submissionId,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'type' => $type,
            'file_size' => $fileSize,
        ]);
    }

    /**
     * Remove media file from submission
     */
    public function removeMedia(int $mediaId, int $userId): bool
    {
        $media = WorkSubmissionMedia::whereHas('workSubmission', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->find($mediaId);

        if (!$media) {
            return false;
        }

        $submission = $media->workSubmission;
        if (!$submission->canBeEdited()) {
            return false;
        }

        // Delete the file from storage
        if (Storage::exists($media->file_path)) {
            Storage::delete($media->file_path);
        }

        return $media->delete();
    }

    /**
     * Approve submission (Admin/KR action)
     */
    public function approveSubmission(int $submissionId): WorkSubmission
    {
        $submission = WorkSubmission::findOrFail($submissionId);

        $submission->update([
            'status' => WorkSubmission::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        return $submission->fresh(['media']);
    }

    /**
     * Reject submission (Admin/KR action)
     */
    public function rejectSubmission(int $submissionId, ?string $reason = null): WorkSubmission
    {
        $submission = WorkSubmission::findOrFail($submissionId);

        $submission->update([
            'status' => WorkSubmission::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);

        return $submission->fresh(['media']);
    }

    /**
     * Reopen submission for resubmission
     */
    public function reopenSubmission(int $submissionId): WorkSubmission
    {
        $submission = WorkSubmission::findOrFail($submissionId);

        if ($submission->isApproved()) {
            throw new \Exception('Cannot reopen an approved submission.');
        }

        $submission->update([
            'status' => WorkSubmission::STATUS_DRAFT,
            'rejection_reason' => null,
        ]);

        return $submission->fresh(['media']);
    }

    /**
     * Check if submission has required content based on request category
     */
    public function hasRequiredContent(WorkSubmission $submission, string $category): array
    {
        $errors = [];

        switch ($category) {
            case 'Survey':
            case 'Essay':
                if (empty($submission->text_content)) {
                    $errors[] = 'Text content is required for this submission type.';
                }
                break;

            case 'Photo':
                $hasImages = $submission->media()
                    ->where('type', WorkSubmissionMedia::TYPE_IMAGE)
                    ->exists();
                if (!$hasImages) {
                    $errors[] = 'At least one photo is required for this submission type.';
                }
                break;

            case 'Video':
                $hasVideos = $submission->media()
                    ->where('type', WorkSubmissionMedia::TYPE_VIDEO)
                    ->exists();
                if (!$hasVideos) {
                    $errors[] = 'At least one video is required for this submission type.';
                }
                break;

            case 'Errand':
                // Errands may require either text or media proof
                $hasContent = !empty($submission->text_content) || $submission->media()->exists();
                if (!$hasContent) {
                    $errors[] = 'Please provide text description or media proof of completed errand.';
                }
                break;
        }

        return $errors;
    }
}
