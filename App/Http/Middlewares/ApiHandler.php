<?php
namespace App\Http\Middlewares;
$Origin = $_SERVER['REMOTE_ADDR'];
$RequestMethod = $_SERVER['REQUEST_METHOD'];
$AllowedOrigins = explode(',', env('ALLOWED_ORIGINS',''));

if (filter_var(env('ALLOW_CORS_FROM_ANYWHERE',false),FILTER_VALIDATE_BOOL)) {
    header('Access-Control-Allow-Origin: *');
} else if (in_array($Origin, $AllowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $Origin);
}

if (env('ALLOW_CREDENTIALS',false)) {
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');



