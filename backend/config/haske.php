<?php

return [
    /*
     * "fake" runs every provider in-process so the whole system works with no keys.
     * "live" talks to VTpass / Baxi / Paystack / Termii using the keys below.
     */
    'mode' => env('HASKE_PROVIDER_MODE', 'fake'),

    /*
     * Switch each integration on separately, so you can test against the real
     * VTpass sandbox while Paystack and SMS stay simulated.
     */
    'drivers' => [
        'bills' => env('HASKE_BILLS_MODE', env('HASKE_PROVIDER_MODE', 'fake')),       // fake | live (VTpass + Baxi)
        'payments' => env('HASKE_PAYMENTS_MODE', env('HASKE_PROVIDER_MODE', 'fake')), // fake | live (Paystack)
        'sms' => env('HASKE_SMS_MODE', env('HASKE_PROVIDER_MODE', 'fake')),           // fake | live (Termii)
    ],

    'limits' => [
        // Daily spend per role, kobo.
        'daily' => [
            'customer' => (int) env('HASKE_DAILY_LIMIT_CUSTOMER', 200_000_00),
            'agent' => (int) env('HASKE_DAILY_LIMIT_AGENT', 2_000_000_00),
            'admin' => (int) env('HASKE_DAILY_LIMIT_CUSTOMER', 200_000_00),
        ],
        'pin_max_attempts' => 3,
        'pin_lock_minutes' => (int) env('HASKE_PIN_LOCK_MINUTES', 30),
        'otp_ttl_minutes' => 5,
        'otp_max_attempts' => 5,
    ],

    // Share of our provider commission passed to agents, per tier (%).
    'agent_tiers' => [
        'bronze' => 50,
        'silver' => 60,
        'gold' => 70,
    ],

    'requery' => [
        // Seconds between status checks, then the last value repeats.
        'backoff' => [20, 40, 60, 120, 300],
        'escalate_after_minutes' => 30,
        'give_up_after_hours' => 24,
    ],

    'alerts' => [
        'provider_low_balance' => (int) env('HASKE_LOW_BALANCE_ALERT', 200_000_00),
        'phones' => array_filter(explode(',', (string) env('HASKE_ALERT_PHONES', ''))),
    ],

    'vtpass' => [
        'base_url' => env('VTPASS_BASE_URL', 'https://sandbox.vtpass.com/api'),
        'api_key' => env('VTPASS_API_KEY'),
        'public_key' => env('VTPASS_PUBLIC_KEY'),
        'secret_key' => env('VTPASS_SECRET_KEY'),
        'connect_timeout' => 5,
        'timeout' => 45,
    ],

    'baxi' => [
        'base_url' => env('BAXI_BASE_URL', 'https://api.staging.baxi-services.com/api/baxipay'),
        'api_key' => env('BAXI_API_KEY'),
        'agent_id' => env('BAXI_AGENT_ID'),
        'connect_timeout' => 5,
        'timeout' => 45,
    ],

    'paystack' => [
        'base_url' => 'https://api.paystack.co',
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'preferred_bank' => env('PAYSTACK_DVA_BANK', 'wema-bank'),
        'email_domain' => env('PAYSTACK_PLACEHOLDER_EMAIL_DOMAIN', 'customers.haske.ng'),
    ],

    'termii' => [
        'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com/api'),
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'Haske'),
        'channel' => env('TERMII_CHANNEL', 'dnd'),
    ],

    'support_whatsapp' => env('HASKE_SUPPORT_WHATSAPP', '+2348000000000'),
];
