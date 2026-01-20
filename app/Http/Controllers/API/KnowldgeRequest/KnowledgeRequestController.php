<?php

namespace App\Http\Controllers\ApI\KnowldgeRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKnowledgeRequest;
use App\Http\Resources\KnowledgeRequestResource;
use App\Services\KnowledgeRequestService;
use Illuminate\Http\Request;

class KnowledgeRequestController extends Controller
{
    //

     public function store(
        StoreKnowledgeRequest $request,
        KnowledgeRequestService $service
    ) {
        $knowledgeRequest = $service->create(
            $request->validated(),
            auth()->user()
        );

        return new KnowledgeRequestResource($knowledgeRequest);
    }
}
