<?php

/*
|--------------------------------------------------------------------------
| Mobile app configuration (Flutter client)
|--------------------------------------------------------------------------
| Served to the app through GET /api/v1/app-config so branding and gateway
| keys can change without shipping a new build.
*/

return [

    'app_name' => env('MOBILE_APP_NAME', env('APP_NAME', 'NAFO')),

    // Bump this and set force_update to make old builds show a blocking notice.
    'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),
    'force_update' => (bool) env('MOBILE_FORCE_UPDATE', false),
    'store_url' => env('MOBILE_STORE_URL'),
    'support_phone' => env('MOBILE_SUPPORT_PHONE'),
    'support_whatsapp' => env('MOBILE_SUPPORT_WHATSAPP'),

    /*
    |--------------------------------------------------------------------------
    | OTP login
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_seconds' => (int) env('OTP_TTL', 300),      // 5 minutes
        'resend_after' => (int) env('OTP_RESEND_AFTER', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'max_per_hour' => (int) env('OTP_MAX_PER_HOUR', 6),

        // Return the OTP in the API response so you can test without an SMS
        // provider. Defaults to on only while APP_DEBUG is on.
        'expose_in_response' => (bool) env('OTP_EXPOSE', env('APP_DEBUG', false)),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS driver: log | msg91 | twilio
    |--------------------------------------------------------------------------
    | "log" writes the message to storage/logs/laravel.log — no credentials
    | needed. Swap the driver in .env when you have a provider.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER', 'NAFOAP'),

        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
            'route' => env('MSG91_ROUTE', '4'),
            'country' => env('MSG91_COUNTRY', '91'),
        ],

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateway (Razorpay)
    |--------------------------------------------------------------------------
    | Leave the keys blank and the app automatically falls back to manual
    | UPI / GPay screenshot upload.
    */
    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'razorpay'),

        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
            'live_mode' => (bool) env('RAZORPAY_LIVE_MODE', true),
            'currency' => env('RAZORPAY_CURRENCY', 'INR'),
            'theme_color' => env('RAZORPAY_THEME', '#C9A227'),

            /*
             | Pin the checkout sheet to UPI only.
             |
             | Off by default. Hiding every other block saves one tap but leaves
             | a customer stranded when UPI is not usable on their device - and
             | iOS in particular does not surface UPI apps the way Android does.
             | Turn it on only if the shop genuinely accepts nothing else.
             */
            'upi_only' => (bool) env('RAZORPAY_UPI_ONLY', false),
        ],

        /*
        |----------------------------------------------------------------------
        | Direct UPI intent - no aggregator
        |----------------------------------------------------------------------
        | The app opens Google Pay / PhonePe / Paytm with a upi://pay link
        | straight to the shop's own VPA. No Razorpay, no per transaction fee,
        | and the money lands in the shop account immediately.
        |
        | The catch, and it is the whole reason these land as PENDING: nothing
        | here can prove the money arrived. The response a UPI app hands back is
        | client side and trivially forged, and without a PSP there is no
        | callback to trust. The office confirms against the bank statement,
        | exactly as it does for an agent's cash collection.
        |
        | The VPA and payee name come from Settings (admin panel), falling back
        | to the first active bank's UPI id.
        */
        'upi' => [
            'enabled' => (bool) env('UPI_DIRECT_ENABLED', true),
            'vpa' => env('UPI_VPA'),
            'payee_name' => env('UPI_PAYEE_NAME'),
        ],

        // Auto-approve gateway payments (signature already verified) instead of
        // leaving them PENDING for an admin.
        'auto_approve_gateway' => (bool) env('PAYMENT_AUTO_APPROVE_GATEWAY', true),
    ],
];
