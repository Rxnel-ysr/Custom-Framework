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

    public function json(mixed $data = [], bool $pretty = false)
    {
        $this->sendHeaders();
        $this->headers->contentType('application/json');
        header('Content-Type: application/json');
        echo json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    public function html(string $content)
    {
        $this->sendHeaders();
        $this->headers->contentType('text/html');
        echo $content;
    }

    public function text(string $content)
    {
        $this->sendHeaders();
        $this->headers->contentType('text/plain');
        echo $content;
    }

    public function xml($data)
    {
        $this->sendHeaders();
        $this->headers->contentType('application/xml');
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

    public function serve(string $filePath, ?string $mimeType = null)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $this->withHeaders()
            ->contentType($mimeType?: mime_content_type($filePath))
            ->contentDisposition('inline', basename($filePath))
            ->contentLength(filesize($filePath));

        $this->sendHeaders();
        readfile($filePath);
        exit;
    }

    public function download(string $filePath, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $fileName = $fileName ?: basename($filePath);
        $this->headers
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->contentLength(filesize($filePath))
            ->contentDescription('File Transfer');

        $this->sendHeaders();
        readfile($filePath);
        exit;
    }

    public function downloadLimit(string $filePath, ?string $fileName = null, int $bytesPerSecond = 1024)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $fileName = $fileName ?: basename($filePath);
        $this->headers
            ->contentType('application/octet-stream')
            ->contentDisposition('attachment', $fileName)
            ->contentLength(filesize($filePath))
            ->contentDescription('File Transfer')
            ->cacheControl('no-cache');

        $this->sendHeaders();
        flush();

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die("Cannot open file.");
        }

        while (!feof($handle)) {
            echo fread($handle, $bytesPerSecond);
            flush();
            usleep(1000000);
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
