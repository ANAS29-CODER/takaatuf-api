<?php
// app/Http/Controllers/API/KnowledgeRequestController.php

namespace App\Http\Controllers\API\KnowldgeRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateKnowledgeRequest;
use App\Services\KnowledgeRequestService;
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

        // Validate media size (images 10MB, videos 100MB)
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
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

        // Calculate budget
        $total = $this->service->calculateBudget(
            $data['pay_per_kp'],
            $data['number_of_kps']
        );

        // Create the request
        $requestModel = $this->service->createRequest([
            'user_id'       => $request->user()->id,
            'category'      => $data['category'],
            'details'       => $data['details'],
            'pay_per_kp'    => $data['pay_per_kp'],
            'number_of_kps' => $data['number_of_kps'],
            'review_fee'    => 5,
            'total_budget'  => $total,
            'neighborhood'  => $data['neighborhood'],
            'status'        => 'pending',
        ]);

        // Store media if uploaded
        if ($request->hasFile('media')) {
            $this->service->storeMedia($requestModel->id, $request->file('media'));
        }

        return response()->json([
            'message'      => 'Request created. Proceed to payment.',
            'request_id'   => $requestModel->id,
            'total_budget' => $total,
        ], 201);
    }
}
?>
