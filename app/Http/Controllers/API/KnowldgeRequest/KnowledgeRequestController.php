<?php
// app/Http/Controllers/API/KnowledgeRequestController.php

namespace App\Http\Controllers\API\KnowldgeRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateKnowledgeRequest;
use App\Http\Resources\KnowledgeRequestResource;
use App\Services\KnowledgeRequestService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KnowledgeRequestController extends Controller
{
    protected $service;

    public function __construct(KnowledgeRequestService $service)
    {
        $this->service = $service;
    }

    public function store(CreateKnowledgeRequest $request)
    {
        $data = $request->validated();

        /**
         * 1️⃣ Validate media size (images 10MB, videos 100MB)
         */
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

        /**
         * 2️⃣ Calculate budget
         */
        $total = $this->service->calculateBudget(
            $data['pay_per_kp'],
            $data['number_of_kps']
        );

        /**
         * 3️⃣ Create knowledge request
         */
        $requestModel = $this->service->createRequest([
            'user_id'       => $request->user()->id,
            'category'      => $data['category'],
            'details'       => $data['details'],
            'pay_per_kp'    => $data['pay_per_kp'],
            'number_of_kps' => $data['number_of_kps'],
            'review_fee'    => config('knowledge_request.review_fee'),
            'total_budget'  => $total,
            'neighborhood'  => $data['neighborhood'],
            'status'        => 'active',
            'progress'      => 0,
            'due_date'      => $data['due_date'] ?? null,
            'created_by'    => $request->user()->id,
            'updated_by'    => $request->user()->id,
        ]);

        /**
         * 4️⃣ Store media + get saved media
         */
        $media = collect();

        if ($request->hasFile('media')) {
            $media = $this->service->storeMedia(
                $requestModel->id,
                $request->file('media')
            );
        }

        /**
         * 5️⃣ Return response with media
         */
        return response()->json([
            'message' => 'Request created. Proceed to payment.',
            'data' => new KnowledgeRequestResource(
                $requestModel->load('media')
            ),
            'payment_url' => route('payment.create', [
                'request_id' => $requestModel->id
            ]),
        ], 201);
    }




    public function index()
    {
        try {
            $user = auth()->user();

            $activeRequests = $this->service->getActiveRequests($user);
            $completedRequests = $this->service->getCompletedRequests($user);

            $isEmpty = $activeRequests->isEmpty() && $completedRequests->isEmpty();

            return response()->json([
                'active_requests' => KnowledgeRequestResource::collection($activeRequests),
                'completed_requests' => KnowledgeRequestResource::collection($completedRequests),
                'empty_state' => $isEmpty ? [
                    'title' => 'No requests yet',
                    'description' => 'You can create Survey, Essay, Photo, Video, or Errand requests.',
                    'cta' => 'Create New Request'
                ] : null
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
