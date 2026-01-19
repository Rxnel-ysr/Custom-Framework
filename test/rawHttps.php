<?php

$host = "example.com";
$port = 443;

$socket = stream_socket_client(
    "tls://{$host}:{$port}",
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    stream_context_create([
        'ssl' => [
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ])
);

if (!$socket) {
    throw new RuntimeException("$errstr ($errno)");
}

$request =
    "GET / HTTP/1.1\r\n" .
    "Host: $host\r\n" .
    "User-Agent: RawPHP/1.0\r\n" .
    "Accept: */*\r\n" .
    "Connection: close\r\n" .
    "\r\n";

fwrite($socket, $request);

$response = '';
while (!feof($socket)) {
    $response .= fgets($socket, 4096);
}

fclose($socket);

echo $response;
