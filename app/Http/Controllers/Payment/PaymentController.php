<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeRequest;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //
     public function create($request_id)
    {
        $request = KnowledgeRequest::findOrFail($request_id);

        return response()->json([
            'message' => 'Proceed to payment',
            'payment_url' => 'https://payment-provider.com/pay?amount=' . $request->total_budget . '&request_id=' . $request->id,
        ]);
    }
}
