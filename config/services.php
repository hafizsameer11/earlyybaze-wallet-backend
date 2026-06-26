<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_PHONE_FROM'),
    ],

    'sentdm' => [
        'api_key' => env('SENT_DM_API_KEY'),
        'base_url' => env('SENT_DM_BASE_URL', 'https://api.sent.dm'),
        'whatsapp_template_id' => env('SENT_DM_WHATSAPP_TEMPLATE_ID'),
        'template_name' => env('SENT_DM_TEMPLATE_NAME'),
        'otp_parameter' => env('SENT_DM_OTP_PARAMETER'),
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SENT_DM_CHANNELS', 'sms,whatsapp'))
        ))),
        'sandbox' => filter_var(env('SENT_DM_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
