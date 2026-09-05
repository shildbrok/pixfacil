<?php



return [



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

    'meta_capi' => [
        'enabled'      => env('META_CAPI_ENABLED', false),
        'pixel_id'     => env('META_PIXEL_ID', ''),
        'access_token' => env('META_ACCESS_TOKEN', ''),
        'test_event_code' => env('META_TEST_EVENT_CODE', ''),
        'version'      => env('META_CAPI_VERSION', 'v18.0'),
        'debug'        => env('META_CAPI_DEBUG', false),
    ],




    'xgate' => [


        'webhook_token' => env('XGATE_WEBHOOK_TOKEN', ''),
    ],



    'security' => [

        'allow_env_edit' => env('ALLOW_ENV_EDIT', false),


        'admin_password_confirm_minutes' => env('ADMIN_PASSWORD_CONFIRM_MINUTES', 15),
    ],

];
