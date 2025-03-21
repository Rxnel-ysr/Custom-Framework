<?php
return [
    'web' => [
        'request_limit' => env('WEB_REQUEST_RATE_LIMIT', 30),
        'request_timeframe' => env('WEB_REQUEST_TIMEFRAME', 60),
        'ban_time' => env('WEB_REQUEST_BAN_TIME', 3000)
    ],
    'api' => [
        'request_limit' => env('API_REQUEST_RATE_LIMIT', 30),
        'request_timeframe' => env('API_REQUEST_TIMEFRAME', 60),
        'ban_time' => env('API_REQUEST_BAN_TIME', 3000)
    ]
];
