<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findByIdempotencyKey(string $key): ?Payment
    {
        return Payment::where('idempotency_key', $key)->first();
    }

    public function findByPaypalOrderId(string $orderId): ?Payment
    {
        return Payment::where('paypal_order_id', $orderId)->first();
    }

    public function findByReferenceId(string $referenceId): ?Payment
    {
        return Payment::where('reference_id', $referenceId)->first();
    }

    public function hasCompletedPayment(int $knowledgeRequestId): bool
    {
        return Payment::where('knowledge_request_id', $knowledgeRequestId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->exists();
    }

    public function updateStatus(Payment $payment, string $status, ?string $failureReason = null): Payment
    {
        $payment->status = $status;

        if ($failureReason !== null) {
            $payment->failure_reason = $failureReason;
        }

        $payment->save();

        return $payment;
    }
}
