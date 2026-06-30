<?php

namespace App\Foundation\Http;

class HttpResponse
{
    private int $statusCode;
    private string $statusText;
    private array $headers = [];
    private string $body;
    private string $rawResponse;
    private bool $isBinary = false;

    public function __construct(string $response, protected array $options = [])
    {
        $this->rawResponse = $response;
        $this->parse($response);
    }

    private function parse(string $response): void
    {
        // Handle case where headers might not be present (binary data)
        if (strpos($response, "\r\n\r\n") === false && strpos($response, "\n\n") === false) {
            // No headers found, treat as raw binary data
            $this->statusCode = 200;
            $this->statusText = 'OK';
            $this->body = $response;
            $this->isBinary = true;
            return;
        }

        // Try different header separators
        $parts = preg_split("/\r\n\r\n|\n\n/", $response, 2);
        $headerSection = $parts[0];
        $this->body = $parts[1] ?? '';

        $lines = preg_split("/\r\n|\n/", $headerSection);
        $statusLine = array_shift($lines);

        // Parse status line
        if (preg_match('/HTTP\/\d\.\d (\d{3}) (.*)/', $statusLine, $matches)) {
            $this->statusCode = (int) $matches[1];
            $this->statusText = $matches[2];
        } else {
            // Default status if not HTTP response
            $this->statusCode = 200;
            $this->statusText = 'OK';
        }

        // Parse headers
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $this->headers[strtolower(trim($key))] = trim($value);
            }
        }

        // Check if content is binary based on Content-Type
        $contentType = $this->getHeader('content-type') ?? '';
        $this->isBinary = $this->isBinaryContentType($contentType) ||
            $this->isLikelyBinaryData($this->body);

        // Handle chunked transfer encoding (only for text content)
        if (
            !$this->isBinary &&
            isset($this->headers['transfer-encoding']) &&
            strtolower($this->headers['transfer-encoding']) === 'chunked'
        ) {
            $this->body = $this->decodeChunked($this->body);
        }

        // Handle Content-Encoding for compressed responses
        $contentEncoding = $this->getHeader('content-encoding') ?? '';
        if (!$this->isBinary && $contentEncoding) {
            $this->body = $this->decodeContent($this->body, $contentEncoding);
        }
    }

    private function isBinaryContentType(string $contentType): bool
    {
        // I guess yeah
        $binaryTypes = [
            'application/octet-stream',
            'application/pdf',
            'application/zip',
            'application/gzip',
            'image/',
            'audio/',
            'video/',
            'font/',
        ];

        foreach ($binaryTypes as $type) {
            if (strpos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyBinaryData(string $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $signatures = [
            "\x89PNG\r\n\x1a\n", // PNG
            "\xff\xd8\xff", // JPEG
            "GIF87a", // GIF
            "GIF89a", // GIF
            "\x25\x50\x44\x46", // PDF
            "\x50\x4B\x03\x04", // ZIP
            "\x50\x4B\x05\x06", // ZIP (empty)
            "\x50\x4B\x07\x08", // ZIP (spanned)
            "\x1F\x8B\x08", // GZIP
        ];

        foreach ($signatures as $sig) {
            if (strpos($data, $sig) === 0) {
                return true;
            }
        }

        // Check for non-printable characters (simple heuristic)
        $sample = substr($data, 0, min(1024, strlen($data)));
        $printable = preg_match('/^[\x20-\x7E\r\n\t]*$/', $sample);

        return !$printable;
    }

    private function decodeChunked(string $body): string
    {
        $decoded = '';
        $offset = 0;

        while ($offset < strlen($body)) {
            $crlfPos = strpos($body, "\r\n", $offset);
            if ($crlfPos === false) break;

            $chunkSizeHex = substr($body, $offset, $crlfPos - $offset);
            $chunkSize = hexdec($chunkSizeHex);

            if ($chunkSize === 0) break;

            $offset = $crlfPos + 2;
            $decoded .= substr($body, $offset, $chunkSize);
            $offset += $chunkSize + 2;
        }

        return $decoded;
    }

    private function decodeContent(string $body, string $encoding): string
    {
        if ($body === '' || $encoding === '') {
            return $body;
        }

        $encodings = array_map('trim', explode(',', strtolower($encoding)));

        // decoding must happen in reverse order
        foreach (array_reverse($encodings) as $enc) {
            switch ($enc) {
                case 'gzip':
                    $decoded = gzdecode($body);
                    break;

                case 'deflate':
                    // some servers send raw deflate, some zlib-wrapped
                    $decoded = @gzinflate($body) ?: @gzuncompress($body);
                    break;

                case 'br':
                    if (function_exists('brotli_uncompress')) {
                        $decoded = @\brotli_uncompress($body);
                        break;
                    }
                    throw new \RuntimeException('Brotli not supported (ext-brotli missing)');
                    break;

                case 'identity':
                    $decoded = $body;
                    break;

                default:
                    // unknown encoding → do NOT corrupt data
                    return $body;
            }

            if ($decoded === false || $decoded === null) {
                return $body; // fail-safe
            }

            $body = $decoded;
        }

        return $body;
    }


    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function saveToFile(string $path): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($path, $this->body) !== false;
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    public function isBinary(): bool
    {
        return $this->isBinary;
    }

    public function isDownload(): bool
    {
        $contentDisposition = $this->getHeader('content-disposition') ?? '';
        return stripos($contentDisposition, 'attachment') !== false;
    }

    public function getFilename(): ?string
    {
        $contentDisposition = $this->getHeader('content-disposition') ?? '';

        if (preg_match('/filename\*?=["\']?([^"\'\s;]+)["\']?/i', $contentDisposition, $matches)) {
            return $matches[1];
        }

        if (preg_match('/filename=["\']?([^"\'\s;]+)["\']?/i', $contentDisposition, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function json(): mixed
    {
        if ($this->isBinary) {
            throw new \RuntimeException('Cannot decode JSON from binary content');
        }
        return json_decode($this->body, ($this->options['json'] ?? 'array') == 'array' ? true : false);
    }

    public function getRaw(): string
    {
        return $this->rawResponse;
    }

    public function getRawBody(): string
    {
        return $this->body;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function __toString(): string
    {
        if ($this->isBinary) {
            return '[Binary data: ' . strlen($this->body) . ' bytes]';
        }
        return $this->body;
    }

    public function getBodyAsBase64(): string
    {
        return base64_encode($this->body);
    }

    public function getBodyLength(): int
    {
        return mb_strlen($this->body);
    }
}
