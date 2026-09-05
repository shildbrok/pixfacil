<?php



return [
    'enabled'        => env('META_CAPI_ENABLED', false),
    'pixel_id'       => env('META_PIXEL_ID'),
    'access_token'   => env('META_ACCESS_TOKEN'),
    'version'        => env('META_API_VERSION', 'v18.0'),
    'test_event_code'=> env('META_TEST_EVENT_CODE'),
];
