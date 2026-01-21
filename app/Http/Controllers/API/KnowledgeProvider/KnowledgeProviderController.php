<?php

namespace App\Http\Controllers\API\KNOWLEDGEpROVIDER;

use App\Http\Controllers\Controller;
use App\Repositories\EarningRepository;
use App\Services\KnowledgeRequestService;
use Illuminate\Http\Request;

class KnowledgeProviderController extends Controller
{
    protected EarningRepository $earningRepo;

    protected KnowledgeRequestService $KrService;



    public function dashboard () {

        $user=user()->auth();

        $totalEarnings= $this->earningRepo->getCurrentEarnings($user->id);

        $activeRequests= $this->KrService->getActiveRequests($user);

        $completeRequests= $this->KrService->getCompletedRequests($user);

    }
}
