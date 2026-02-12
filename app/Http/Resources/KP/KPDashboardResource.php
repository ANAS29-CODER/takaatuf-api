<?php

namespace App\Http\Resources\KP;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KPDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasActiveRequests = $this->resource['has_active_requests'];

        // Build sections in order based on active requests
        $sections = [];

        // Earnings summary always first
        $sections['earnings_summary'] = [
            'amount' => $this->resource['earnings_summary']['formatted'],
            'amount_raw' => $this->resource['earnings_summary']['amount'],
            'currency' => $this->resource['earnings_summary']['currency'],
            'description' => 'Total earned since last payout',
        ];

        // Section ordering based on active requests
        if ($hasActiveRequests) {
            // Active requests appear above available requests
            $sections['active_requests'] = $this->formatActiveRequests();
            $sections['available_requests'] = $this->formatAvailableRequests();
        } else {
            // Available requests appear first when no active requests
            $sections['available_requests'] = $this->formatAvailableRequests();
            $sections['active_requests'] = $this->formatActiveRequests();
        }

        $sections['pending_requests'] = $this->formatPendingRequests();

        // Completed requests always at bottom
        $sections['completed_requests'] = $this->formatCompletedRequests();

        // Add section order for frontend reference
        $sections['section_order'] = $hasActiveRequests
            ? ['earnings_summary', 'active_requests', 'available_requests', 'completed_requests']
            : ['earnings_summary', 'available_requests', 'active_requests', 'completed_requests'];

        return $sections;
    }

    /**
     * Format active requests section
     */
    protected function formatActiveRequests(): array
    {
        $items = $this->resource['active_requests'];

        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'items' => [],
                'empty_message' => 'You are not currently working on any requests.',
            ];
        }

        return [
            'count' => $items->count(),
            'items' => ActiveRequestResource::collection($items),
            'empty_message' => null,
        ];
    }

    /**
     * Format pending requests section
     */
    protected function formatPendingRequests(): array
    {
        $items = $this->resource['pending_requests'];
        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'items' => [],
                'empty_message' => 'You have no pending requests at the moment.',
            ];
        }

        return [
            'count' => $items->count(),
            'items' => PendingRequestResource::collection($items),
            'empty_message' => null,
        ];
    }

    /**
     * Format available requests section
     */
    protected function formatAvailableRequests(): array
    {
        $items = $this->resource['available_requests'];

        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'items' => [],
                'empty_message' => 'No new requests are currently available.',
            ];
        }

        return [
            'count' => $items->count(),
            'items' => AvailableRequestResource::collection($items),
            'empty_message' => null,
        ];
    }

    /**
     * Format completed requests section
     */
    protected function formatCompletedRequests(): array
    {
        $completedData = $this->resource['completed_requests'];
        $items = $completedData['items'];

        if ($items->isEmpty()) {
            return [
                'total_count' => 0,
                'collapsed' => true,
                'items' => [],
                'empty_message' => 'No completed requests yet.',
            ];
        }

        return [
            'total_count' => $completedData['total_count'],
            'collapsed' => true,
            'items' => CompletedRequestResource::collection($items),
            'empty_message' => null,
        ];
    }
}
