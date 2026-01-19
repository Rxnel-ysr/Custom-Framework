<?php

$host = "example.com";
$port = 80;

$socket = fsockopen($host, $port, $errno, $errstr, 5);
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
