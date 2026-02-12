<?php

namespace App\Http\Resources\KP;

use App\Models\UserKnowledgeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TaskPageResource extends JsonResource
{
    protected $submission;
    protected $canEdit;
    protected $isReadOnly;
    protected $statusInfo;

    public function __construct($resource, $submission = null, $canEdit = false, $isReadOnly = false, $statusInfo = [])
    {
        parent::__construct($resource);
        $this->submission = $submission;
        $this->canEdit = $canEdit;
        $this->isReadOnly = $isReadOnly;
        $this->statusInfo = $statusInfo;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Request details
            'request' => [
                'category' => $this->category,
                'details' => $this->details,
                'neighborhood' => $this->neighborhood,
                'budget' => [
                    'total' => number_format((float) $this->total_budget, 2),
                    'total_raw' => (float) $this->total_budget,
                    'my_payout' => number_format((float) ($this->kp_payout_amount ?? $this->pay_per_kp), 2),
                    'my_payout_raw' => (float) ($this->kp_payout_amount ?? $this->pay_per_kp),
                ],
                'kps_assigned' => $this->total_kps_assigned ?? 0,
                'total_kps_needed' => $this->number_of_kps,
                'created_at' => $this->created_at->format('Y-m-d'),
                'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
                'media' => $this->formatRequestMedia(),
            ],

            // Progress tracking
            'progress' => [
                'percentage' => $this->kp_progress ?? 0,
                'is_completed' => ($this->kp_progress ?? 0) >= 100,
            ],

            // Status information
            'status' => [
                'assignment_status' => $this->kp_status ?? UserKnowledgeRequest::STATUS_PENDING,
                'assignment_status_label' => $this->getStatusLabel(),
                'submission_status' => $this->submission?->status,
                'submission_status_label' => $this->getSubmissionStatusLabel(),
                'rejection_reason' => $this->submission?->rejection_reason,
                'can_resubmit' => $this->submission?->isRejected() ?? false,
            ],

            // Submission data
            'submission' => $this->formatSubmission(),

            // Permissions
            'permissions' => [
                'can_edit' => $this->canEdit,
                'is_read_only' => $this->isReadOnly,
                'can_save_draft' => $this->canEdit && !$this->isReadOnly,
                'can_submit' => $this->canEdit && !$this->isReadOnly,
                'can_upload_media' => $this->canEdit && !$this->isReadOnly,
            ],
        ];
    }

    /**
     * Format request media for display
     */
    protected function formatRequestMedia(): array
    {
        if (!$this->media) {
            return [];
        }

        return $this->media->map(function ($media) {
            return [
                'id' => $media->id,
                'type' => $media->type,
                'url' => Storage::url($media->file_path),
            ];
        })->toArray();
    }

    /**
     * Format submission data
     */
    protected function formatSubmission(): ?array
    {
        if (!$this->submission) {
            return null;
        }

        return [
            'id' => $this->submission->id,
            'text_content' => $this->submission->text_content,
            'status' => $this->submission->status,
            'submitted_at' => $this->submission->submitted_at?->toDateTimeString(),
            'reviewed_at' => $this->submission->reviewed_at?->toDateTimeString(),
            'media' => $this->formatSubmissionMedia(),
        ];
    }

    /**
     * Format submission media
     */
    protected function formatSubmissionMedia(): array
    {
        if (!$this->submission || !$this->submission->media) {
            return [];
        }

        return $this->submission->media->map(function ($media) {
            return [
                'id' => $media->id,
                'original_name' => $media->original_name,
                'type' => $media->type,
                'url' => Storage::url($media->file_path),
                'file_size' => $media->formatted_file_size,
            ];
        })->toArray();
    }

    /**
     * Get human-readable status label
     */
    protected function getStatusLabel(): string
    {
        return match ($this->kp_status ?? UserKnowledgeRequest::STATUS_PENDING) {
            UserKnowledgeRequest::STATUS_PENDING => 'Pending',
            UserKnowledgeRequest::STATUS_IN_PROGRESS => 'In Progress',
            UserKnowledgeRequest::STATUS_AWAITING_REVIEW => 'Submitted',
            UserKnowledgeRequest::STATUS_COMPLETED => 'Completed',
            UserKnowledgeRequest::STATUS_APPROVED => 'Approved',
            UserKnowledgeRequest::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->kp_status ?? 'pending')),
        };
    }

    /**
     * Get submission status label
     */
    protected function getSubmissionStatusLabel(): ?string
    {
        if (!$this->submission) {
            return null;
        }

        return match ($this->submission->status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->submission->status),
        };
    }
}
