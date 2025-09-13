<?php

namespace App\Foundation\Http;

use SimpleXMLElement;

class Response
{
    protected HttpHeaders $headers;

    public function __construct()
    {
        $this->headers = new HttpHeaders();
    }

    public function withHeaders(): HttpHeaders
    {
        return $this->headers;
    }

    public function json(mixed $data = [], int $code = 200,bool $pretty = false)
    {
        $this->headers->contentType('application/json');
        $this->status($code);
        $this->sendHeaders();
        
        echo json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    public function html(string $content)
    {
        $this->headers->contentType('text/html');
        $this->sendHeaders();
        echo $content;
    }

    public function text(string $content)
    {
        $this->headers->contentType('text/plain');
        $this->sendHeaders();
        echo $content;
    }

    public function xml($data)
    {
        $this->headers->contentType('application/xml');
        $this->sendHeaders();
        $xml = new SimpleXMLElement('<response />');
        array_walk_recursive($data, function ($value, $key) use ($xml) {
            $xml->addChild($key, $value);
        });
        echo $xml->asXML();
    }

    public function redirect(string $url, int $code = 302)
    {
        $this->sendHeaders();
        http_response_code($code);
        header("Location: $url");
        exit;
    }

    /**
     * Stream file directly to browser
     */
    public function stream(string $filePath, ?string $mimeType = null, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $this->withHeaders()
            ->contentType($mimeType ?: mime_content_type($filePath))
            ->contentDisposition('inline', $fileName ?: basename($filePath))
            ->contentLength(filesize($filePath));

        $this->sendHeaders();
        readfile($filePath);
        exit;
    }

    /**
     * Download a file with support for resuming
     */
    public function download(string $filePath, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $fileName = $fileName ?: basename($filePath);
        $fileSize = filesize($filePath);
        // echo $fileSize;
        // die($fileSize);
        $start = 0;
        $end = $fileSize - 1;

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
                $start = (int)$matches[1];
                if (isset($matches[2])) {
                    $end = (int)$matches[2];
                }
            }
        }

        $length = $end - $start + 1;

        $this->headers
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->contentRange($fileSize, $start, $end)
            ->contentLength($length)
            ->contentDescription('File Transfer');

        if ($start > 0 || $end < $fileSize - 1) {
            $this->status(206); // Partial Content
        }

        $this->sendHeaders();

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die("Cannot open file.");
        }

        fseek($handle, $start);
        $remaining = $length;
        $chunkSize = 8192;

        while (!feof($handle) && $remaining > 0) {
            if(connection_aborted()){
                break;
            }
            $readSize = min($chunkSize, $remaining);
            echo fread($handle, $readSize);
            flush();
            $remaining -= $readSize;
        }

        fclose($handle);
        exit;
    }

    /**
     * Download a file with time-based rate limiting and resume support
     * @param string $filePath Path to the file
     * @param string|null $fileName Custom filename (optional)
     * @param int $minutes Time to complete download (in minutes)
     */
    public function downloadLimit(string $filePath, ?string $fileName = null, int $minutes = 1)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        // Ensure minimum time is 1 second (avoid division by zero)
        $minutes = max(1, $minutes);

        $fileName = $fileName ?: basename($filePath);
        $fileSize = filesize($filePath);
        $start = 0;
        $end = $fileSize - 1;

        // Handle range requests
        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
                $start = (int)$matches[1];
                if (isset($matches[2])) {
                    $end = min((int)$matches[2], $fileSize - 1);
                }
                $start = min($start, $fileSize - 1);
            }
        }


        $length = $end - $start + 1;
        $bytesPerSecond = max(1, $length / ($minutes * 60));

        $this->headers
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->contentRange($fileSize, $start, $end)
            ->contentLength($length)
            ->contentDescription('File Transfer')
            ->cacheControl('no-cache')
            ->customHeader('Accept-Ranges', 'bytes');

        if ($start > 0 || $end < $fileSize - 1) {
            $this->status(206);
        }

        $this->sendHeaders();

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die("Cannot open file.");
        }

        fseek($handle, $start);
        $remaining = $length;
        $chunkSize = 8192; // 8KB chunks
        $microseconds = ($chunkSize / $bytesPerSecond) * 1000000;

        while (!feof($handle) && $remaining > 0) {
            $currentChunkSize = min($chunkSize, $remaining);
            $startTime = microtime(true);

            echo fread($handle, $currentChunkSize);
            flush();

            $remaining -= $currentChunkSize;

            if (connection_aborted()) {
                break;
            }

            $elapsed = microtime(true) - $startTime;
            $sleepTime = $microseconds - ($elapsed * 1000000);

            if ($sleepTime > 0) {
                usleep((int)$sleepTime);
            }
        }

        fclose($handle);
        exit;
    }

    public function status(int $code)
    {
        http_response_code($code);
        return $this;
    }

    public function make(string $content = '', int $status = 200, array $headers = [])
    {
        http_response_code($status);
        $this->headers->merge($headers);
        $this->sendHeaders();
        echo $content;
    }

    protected function sendHeaders()
    {
        foreach ($this->headers->build() as $key => $value) {
            header("$key: $value");
        }
    }
}
