<?php

namespace App\Foundation\Http;

use SimpleXMLElement;

class Response
{
    public HttpHeaders $headers;
    private int $code = 200;
    private mixed $content = null;
    private bool $sent = false;
    private bool $shouldExit = false;
    private bool $isStream = false;
    private bool $isDownload = false;
    private bool $isLimitedDownload = false;
    private ?string $downloadFileName = null;
    private int $downloadMinutes = 0;
    

    public function __construct()
    {
        $this->headers = new HttpHeaders();
    }

    public function withHeaders(): HttpHeaders
    {
        return $this->headers;
    }

    public function sendStatus()
    {
        http_response_code($this->code);
        return $this;
    }

    public function json(mixed $data = [], int $code = 200, array $headers = [], bool $pretty = false)
    {
        $this->headers->contentType('application/json');
        foreach ($headers as $header => $value) {
            $this->headers->set($header, $value);
        }
        $this->status($code);

        $this->content = json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
        return $this;
    }

    public function html(string $content)
    {
        $this->headers->contentType('text/html');
        $this->content = $content;
        return $this;
    }

    public function text(string $content)
    {
        $this->headers->contentType('text/plain');
        $this->content = $content;
        return $this;
    }

    public function xml(string $data = <<<XML
    <xml>
    </xml>
    XML)
    {
        $this->headers->contentType('application/xml');
        // $xml = new SimpleXMLElement('<response />');
        // array_walk_recursive($data, function ($value, $key) use ($xml) {
        //     $xml->addChild($key, $value);
        // });
        $this->content = $data;
        return $this;
    }

    public function redirect(string $url, int $code = 302)
    {
        $this->status($code);
        $this->headers->set('Location', $url);
        $this->shouldExit = true;
        return $this;
    }

    /**
     * Stream file directly to browser
     */
    public function stream(string $filePath, ?string $mimeType = null, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            return $this->status(404)->text("File not found.");
        }

        $this->withHeaders()
            ->contentType($mimeType ?: mime_content_type($filePath))
            ->contentDisposition('inline', $fileName ?: basename($filePath))
            ->contentLength(filesize($filePath));

        $this->content = $filePath;
        $this->isStream = true;
        return $this;
    }

    /**
     * Download a file with support for resuming
     */
    public function download(string $filePath, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            return $this->status(404)->text("File not found.");
        }

        $this->content = $filePath;
        $this->isDownload = true;
        $this->downloadFileName = $fileName ?: basename($filePath);
        return $this;
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
            return $this->status(404)->text("File not found.");
        }

        $this->content = $filePath;
        $this->isDownload = true;
        $this->isLimitedDownload = true;
        $this->downloadFileName = $fileName ?: basename($filePath);
        $this->downloadMinutes = $minutes;
        return $this;
    }

    public function status(int $code)
    {
        $this->code = $code;
        return $this;
    }

    public function make(string $content = '', int $status = 200, array $headers = [])
    {
        $this->status($status);
        $this->headers->merge($headers);
        $this->content = $content;
        return $this;
    }

    public function sendHeaders()
    {
        foreach ($this->headers->build() as $key => $value) {
            header("$key: $value");
        }
        return $this;
    }

    /**
     * Send the response
     * This is the main method the router should call
     */
    public function send()
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;
        $this->sendStatus()->sendHeaders();

        // Handle redirect
        if (isset($this->shouldExit) && $this->shouldExit) {
            exit;
        }

        // Handle file downloads/streams
        if (isset($this->isDownload) && $this->isDownload) {
            $this->sendDownload();
        } elseif (isset($this->isStream) && $this->isStream) {
            $this->sendStream();
        } else {
            // Handle regular content
            echo $this->content;
        }
    }

    /**
     * Send stream response
     */
    private function sendStream()
    {
        readfile($this->content);
        exit;
    }

    /**
     * Send download response
     */
    private function sendDownload()
    {
        $filePath = $this->content;
        $fileName = $this->downloadFileName ?? basename($filePath);
        $fileSize = filesize($filePath);

        if (isset($this->isLimitedDownload) && $this->isLimitedDownload) {
            $this->sendLimitedDownload($filePath, $fileName, $fileSize);
        } else {
            $this->sendRegularDownload($filePath, $fileName, $fileSize);
        }

        exit;
    }

    /**
     * Send regular download with resume support
     */
    private function sendRegularDownload(string $filePath, string $fileName, int $fileSize)
    {
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

        $this->withHeaders()
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->set('Accept-Ranges', 'bytes')
            ->contentRange($fileSize, $start, $end)
            ->contentLength($length)
            ->contentDescription('File Transfer');

        if ($start > 0 || $end < $fileSize - 1) {
            $this->status(206)->sendStatus(); // Partial Content
        }

        $this->sendHeaders();

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $this->status(500)->sendStatus();
            die("Cannot open file.");
        }

        fseek($handle, $start);
        $remaining = $length;
        $chunkSize = 8192;

        while (!feof($handle) && $remaining > 0) {
            if (connection_aborted()) {
                break;
            }
            $readSize = min($chunkSize, $remaining);
            echo fread($handle, $readSize);
            flush();
            $remaining -= $readSize;
        }

        fclose($handle);
    }

    /**
     * Send download with time-based rate limiting
     */
    private function sendLimitedDownload(string $filePath, string $fileName, int $fileSize)
    {
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

        // Calculate bytes per second, ensure minimum 1KB/s
        $totalSeconds = $this->downloadMinutes * 60;
        $bytesPerSecond = max(1024, $length / $totalSeconds); // Minimum 1KB/s

        // Calculate sleep time between chunks (in microseconds)
        $chunkSize = 8192; // 8KB chunks
        $chunksPerSecond = $bytesPerSecond / $chunkSize;
        $microsecondsPerChunk = $chunksPerSecond > 0 ? (1000000 / $chunksPerSecond) : 0;

        // Set headers
        $this->headers
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->set('Accept-Ranges', 'bytes')
            ->cacheControl('no-cache')
            ->contentDescription('File Transfer');

        // Add Content-Range only for partial content
        if ($start > 0 || $end < $fileSize - 1) {
            $this->headers->contentRange($fileSize, $start, $end);
            $this->status(206); // Partial Content
        } else {
            $this->headers->contentLength($length);
        }

        $this->sendStatus()->sendHeaders();

        // Open file
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $this->status(500)->sendStatus();
            die("Cannot open file.");
        }

        // Seek to start position
        fseek($handle, $start);
        $remaining = $length;

        // Disable PHP time limit for long downloads
        set_time_limit(0);

        // Track time for consistent throttling
        $lastChunkTime = microtime(true);

        while (!feof($handle) && $remaining > 0) {
            $currentChunkSize = min($chunkSize, $remaining);

            echo fread($handle, $currentChunkSize);

            // Force output immediately
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $remaining -= $currentChunkSize;

            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }

            // Throttle if needed
            if ($microsecondsPerChunk > 0) {
                $elapsed = microtime(true) - $lastChunkTime;
                $sleepTime = $microsecondsPerChunk - ($elapsed * 1000000);

                if ($sleepTime > 1000) { // Only sleep if > 1ms
                    usleep((int)$sleepTime);
                }
            }

            $lastChunkTime = microtime(true);
        }

        fclose($handle);
    }
}
