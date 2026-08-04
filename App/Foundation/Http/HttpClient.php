<?php

namespace App\Foundation\Http;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Dep;

#[Dep(HttpResponse::class)]
class HttpClient
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_USER_AGENT = 'RawPHP/2.0';
    private const BUFFER_SIZE = 8192;
    private const MAX_REDIRECTS = 10;
    private const FOLLOW_LOCATION = true;
    private const MAX_BODY_SIZE = 10485760; // 10MB max body size for safety

    /**
     * @param array{
     *  user_agent: string,
     *  ca_file: string,
     *  ca_path: string,
     *  json: 'object'|'array', 
     *  timeout: int, 
     *  follow_redirects: bool, 
     *  max_redirects: integer, 
     *  verify_peer: bool, 
     *  verify_peer_name: bool, 
     *  allow_self_signed: bool, 
     *  binary_download: bool
     * } $options
     */
    public function __construct(
        protected array $options = []
    ) {}

    public function get(string $url, array $params = [], array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $params, [], $headers);
    }

    public function post(string $url, mixed $data = [], array $params = [], array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $params, $data, $headers);
    }

    public function put(string $url, mixed $data = [], array $params = [], array $headers = []): HttpResponse
    {
        return $this->request('PUT', $url, $params, $data, $headers);
    }

    public function delete(string $url, array $params = [], array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $url, $params, [], $headers);
    }

    public function patch(string $url, mixed $data = [], array $params = [], array $headers = []): HttpResponse
    {
        return $this->request('PATCH', $url, $params, $data, $headers);
    }

    public function requestBinary(string $url, array $params = [], array $headers = []): HttpResponse
    {
        $headers['Accept'] = '*/*';
        return $this->request('GET', $url, $params, [], $headers);
    }

    public function download(string $url, string $savePath, array $params = [], array $headers = []): bool
    {
        $headers['Accept'] = '*/*';
        $response = $this->request('GET', $url, $params, [], $headers);

        if (!$response->isSuccess()) {
            return false;
        }

        return $response->saveToFile($savePath);
    }

    public function setUserAgent(string $userAgent)
    {
        $this->options['user_agent'] = $userAgent;
        return $this;
    }

    protected function request(
        string $method,
        string $url,
        array $params = [],
        mixed $data = [],
        array $headers = []
    ): HttpResponse {
        $followRedirects = $this->options['follow_redirects'] ?? self::FOLLOW_LOCATION;
        $maxRedirects = $this->options['max_redirects'] ?? self::MAX_REDIRECTS;
        $redirectCount = 0;

        $originalHeaders = $headers;
        $originalData = $data;

        do {
            $parsed = parse_url($url);
            if (!$parsed) {
                throw new InvalidArgumentException('Invalid URL provided');
            }

            if (!isset($parsed['host'])) {
                if (!isset($_SERVER['HTTP_HOST'])) {
                    throw new InvalidArgumentException('No host specified and cannot determine from environment');
                }
                $parsed['host'] = $_SERVER['HTTP_HOST'];
                $parsed['scheme'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            }

            $socket = $this->createSocket($parsed);
            $uri = $this->buildUri($parsed, $params);

            // For redirects, some methods should not have body resubmitted
            if ($redirectCount > 0 && in_array($method, ['GET', 'HEAD'])) {
                $data = [];
            } else {
                $data = $originalData;
            }

            $body = $this->prepareBody($data, $headers);
            $request = $this->buildRequest($method, $uri, $parsed['host'], $headers, $body);

            fwrite($socket, $request);

            // For binary downloads, read response in chunks
            $expectBinary = isset($headers['Accept']) && $headers['Accept'] === '*/*';
            $response = $this->readResponse($socket, $expectBinary);
            fclose($socket);

            $httpResponse = new HttpResponse($response, $this->options);

            // Check for redirect
            if ($followRedirects && $this->shouldRedirect($httpResponse, $method)) {
                $redirectCount++;
                if ($redirectCount > $maxRedirects) {
                    throw new RuntimeException("Too many redirects (max: {$maxRedirects})");
                }

                $location =  $httpResponse->getHeader('location');
                if (!$location) {
                    throw new RuntimeException('Redirect response without Location header');
                }

                // Handle relative redirects
                $url = $this->resolveRedirectUrl($location, $parsed);

                // For POST redirects, change to GET (following common browser behavior)
                if ($method === 'POST' && in_array($httpResponse->getStatusCode(), [301, 302, 303])) {
                    $method = 'GET';
                    $originalData = [];
                }

                // Preserve some headers but not all (e.g., don't preserve Content-Type for GET redirects)
                if ($method === 'GET') {
                    unset($originalHeaders['content-type']);
                }
                $headers = $originalHeaders;

                continue;
            }

            return $httpResponse;
        } while ($followRedirects && $redirectCount <= $maxRedirects);

        // This should never be reached, but just in case
        return $httpResponse ?? new HttpResponse('');
    }

    protected function shouldRedirect(HttpResponse $response, string $method): bool
    {
        $status = $response->getStatusCode();

        // 301, 302, 303, 307, 308 are redirect statuses
        if (!in_array($status, [301, 302, 303, 307, 308])) {
            return false;
        }

        // 307 and 308 preserve the original method
        if (in_array($status, [307, 308])) {
            return true;
        }

        // For 301, 302, 303, allow redirect
        return true;
    }

    protected function resolveRedirectUrl(string $location, array $currentParsed): string
    {
        // Absolute URL
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        // Protocol-relative URL
        if (strpos($location, '//') === 0) {
            return $currentParsed['scheme'] . ':' . $location;
        }

        // Absolute path
        if (strpos($location, '/') === 0) {
            return $currentParsed['scheme'] . '://' . $currentParsed['host'] .
                (isset($currentParsed['port']) ? ':' . $currentParsed['port'] : '') .
                $location;
        }

        // Relative path
        $path = isset($currentParsed['path']) ? dirname($currentParsed['path']) : '/';
        if ($path === '\\' || $path === '.') {
            $path = '/';
        }
        $path = rtrim($path, '/') . '/' . $location;

        return $currentParsed['scheme'] . '://' . $currentParsed['host'] .
            (isset($currentParsed['port']) ? ':' . $currentParsed['port'] : '') .
            $path;
    }

    protected function createSocket(array $parsed)
    {
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'];
        $timeout = $this->options['timeout'] ?? self::DEFAULT_TIMEOUT;

        try {
            if ($scheme === 'http') {
                $port = $parsed['port'] ?? 80;
                $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            } else {
                $port = $parsed['port'] ?? 443;
                $context = $this->createSslContext();
                $socket = stream_socket_client(
                    "tls://{$host}:{$port}",
                    $errno,
                    $errstr,
                    $timeout,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            }
        } catch (Throwable $th) {
            throw new \Exception("Connection couldn't not be established: " . $th->getMessage(), 1, $th);
        }
        if (!$socket) {
            throw new RuntimeException("Connection failed to {$host}:{$port} [{$errno}] {$errstr}");
        }

        stream_set_timeout($socket, $timeout);
        stream_set_blocking($socket, true);

        return $socket;
    }

    protected function createSslContext()
    {
        $sslOptions = [
            'verify_peer' => $this->options['verify_peer'] ?? true,
            'verify_peer_name' => $this->options['verify_peer_name'] ?? true,
            'allow_self_signed' => $this->options['allow_self_signed'] ?? false,
        ];

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $sslOptions['crypto_method'] = STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if (isset($this->options['ca_file'])) {
            $sslOptions['cafile'] = $this->options['ca_file'];
        }

        if (isset($this->options['ca_path'])) {
            $sslOptions['capath'] = $this->options['ca_path'];
        }

        // For binary downloads, disable SSL compression for better performance
        if (isset($this->options['binary_download']) && $this->options['binary_download']) {
            $sslOptions['disable_compression'] = true;
        }

        return stream_context_create(['ssl' => $sslOptions]);
    }

    protected function buildUri(array $parsed, array $params): string
    {
        $uri = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? '';

        $queryParams = [];

        if ($query) {
            parse_str($query, $existingParams);
            $queryParams = $existingParams;
        }

        $queryParams = array_merge($queryParams, $params);

        if (!empty($queryParams)) {
            $uri .= '?' . http_build_query($queryParams);
        }

        return $uri;
    }

    protected function prepareBody(mixed $data, array &$headers): string
    {
        if (empty($data)) {
            return '';
        }

        $contentTypeSet = false;
        if (isset($headers['content-type'])) $contentTypeSet = true;

        // Handle binary data (file uploads)
        if (is_resource($data) && get_resource_type($data) === 'stream') {
            if (!$contentTypeSet) {
                $headers['content-type'] = 'application/octet-stream';
            }

            // Read stream content
            $content = '';
            rewind($data);
            while (!feof($data)) {
                $content .= fread($data, 8192);
            }
            return $content;
        }

        // Handle multipart form data with files
        if (is_array($data) && $this->hasFiles($data)) {
            $boundary = '----' . uniqid();
            $headers['content-type'] = 'multipart/form-data; boundary=' . $boundary;
            return $this->buildMultipartBody($data, $boundary);
        }

        if (is_array($data)) {
            if (!$contentTypeSet) {
                $headers['content-type'] = 'application/x-www-form-urlencoded';
            }
            return http_build_query($data);
        }

        if (is_string($data)) {
            if (!$contentTypeSet) {
                if ($this->isJson($data)) {
                    $headers['content-type'] = 'application/json';
                } else {
                    $headers['content-type'] = 'text/plain';
                }
            }
            return $data;
        }

        if (!$contentTypeSet) {
            $headers['content-type'] = 'application/json';
        }
        return json_encode($data);
    }

    protected function hasFiles(array $data): bool
    {
        foreach ($data as $value) {
            if (is_resource($value) || (is_array($value) && isset($value['tmp_name']))) {
                return true;
            }
        }
        return false;
    }

    protected function buildMultipartBody(array $data, string $boundary): string
    {
        $body = '';

        foreach ($data as $name => $value) {
            $body .= "--{$boundary}\r\n";

            if (is_array($value) && isset($value['tmp_name'])) {
                // Handle file upload
                $filename = $value['name'] ?? 'file';
                $contentType = $value['type'] ?? 'application/octet-stream';
                $fileContent = file_get_contents($value['tmp_name']);

                $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
                $body .= "Content-Type: {$contentType}\r\n\r\n";
                $body .= $fileContent . "\r\n";
            } elseif (is_resource($value)) {
                // Handle resource
                $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"upload.bin\"\r\n";
                $body .= "Content-Type: application/octet-stream\r\n\r\n";
                rewind($value);
                while (!feof($value)) {
                    $body .= fread($value, 8192);
                }
                $body .= "\r\n";
            } else {
                // Regular form field
                $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
                $body .= $value . "\r\n";
            }
        }

        $body .= "--{$boundary}--\r\n";
        return $body;
    }

    protected function isJson(string $string): bool
    {
        try {
            json_decode($string, null, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (Throwable $th) {
            return false;
        }
    }

    protected function buildRequest(
        string $method,
        string $uri,
        string $host,
        array $headers,
        string $body
    ): string {
        $requestHeaders = [
            "{$method} {$uri} HTTP/1.1",
            "host: {$host}",
            "user-agent: " . ($this->options['user_agent'] ?? self::DEFAULT_USER_AGENT),
            "connection: close",
        ];

        foreach ($headers as $key => $value) {
            $requestHeaders[] = "{$key}: {$value}";
        }

        if (!empty($body)) {
            $requestHeaders[] = "content-length: " . strlen($body);
        }

        $request = implode("\r\n", $requestHeaders) . "\r\n\r\n";

        if (!empty($body)) {
            $request .= $body;
        }

        return $request;
    }

    protected function readResponse($socket, bool $expectBinary = false): string
    {
        $response = '';
        $body = '';
        $inBody = false;
        $contentLength = null;
        $isChunked = false;
        $bodySize = 0;

        while (!feof($socket)) {
            $line = fgets($socket, self::BUFFER_SIZE);
            if ($line === false) {
                break;
            }

            if (!$inBody) {
                $response .= $line;
                if ($line === "\r\n" || $line === "\n") {
                    $inBody = true;

                    // Parse headers from response
                    $headerLines = explode("\r\n", $response);
                    foreach ($headerLines as $headerLine) {
                        if (stripos($headerLine, 'content-length:') === 0) {
                            $contentLength = (int) trim(substr($headerLine, 15));
                        }
                        if (
                            stripos($headerLine, 'transfer-encoding:') === 0 &&
                            stripos($headerLine, 'chunked') !== false
                        ) {
                            $isChunked = true;
                        }
                    }

                    // For binary responses with known length, read exact amount
                    if ($expectBinary && $contentLength !== null && $contentLength > 0) {
                        $body = fread($socket, $contentLength);
                        $response .= $body;
                        break;
                    }
                }
            } else {
                $response .= $line;
                $body .= $line;
                $bodySize += strlen($line);

                // Safety check for maximum body size
                if ($bodySize > self::MAX_BODY_SIZE) {
                    throw new RuntimeException('Response body exceeds maximum allowed size');
                }

                // If we know content length and have read it all, break
                if ($contentLength !== null && strlen($body) >= $contentLength) {
                    break;
                }

                // For chunked encoding, look for end marker
                if ($isChunked && strpos($body, "0\r\n\r\n") !== false) {
                    break;
                }
            }
        }

        $metadata = stream_get_meta_data($socket);
        if ($metadata['timed_out']) {
            throw new RuntimeException('Request timed out');
        }

        return $response;
    }
}
