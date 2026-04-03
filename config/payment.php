<?php

return [

    'system_fee' => (float) env('PAYMENT_SYSTEM_FEE', 5.00),

    'payment_fee_percentage' => (float) env('PAYMENT_FEE_PERCENTAGE', 0.03),

    'payment_fee_fixed' => (float) env('PAYMENT_FEE_FIXED', 0.49),

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ],

];
