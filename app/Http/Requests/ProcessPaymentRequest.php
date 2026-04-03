<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paypal_order_id' => ['required', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'turnstile_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.gt' => 'The amount must be greater than 0.',
            'amount.regex' => 'The amount must have up to 2 decimal places.',
            'paypal_order_id.required' => 'The PayPal order ID is required.',
            'idempotency_key.required' => 'An idempotency key is required to prevent duplicate payments.',
            'turnstile_token.required' => 'CAPTCHA verification is required.',
        ];
    }
}
