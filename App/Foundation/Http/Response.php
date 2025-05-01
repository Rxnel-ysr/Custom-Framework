<?php

namespace App\Foundation\Http;

use SimpleXMLElement;

class Response
{
    public function json(mixed $data = [], bool $pretty = false)
    {
        header('Content-Type: application/json');
        echo json_encode($data, ($pretty) ? JSON_PRETTY_PRINT : 0);
    }

    public function html(string $content)
    {
        header('Content-Type: text/html');
        echo $content;
    }

    public function text(string $content)
    {
        header('Content-Type: text/plain');
        echo $content;
    }

    public function xml($data)
    {
        header('Content-Type: application/xml');
        $xml = new SimpleXMLElement('<response />');
        array_walk_recursive($data, function ($value, $key) use ($xml) {
            $xml->addChild($key, $value);
        });
        echo $xml->asXML();
    }

    public function redirect(string $url, int $code = 302)
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }

    public function download(string $filePath, ?string $fileName = null)
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die("File not found.");
        }

        $fileName = $fileName ?: basename($filePath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
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
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
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


    public function header(string $key, string $value)
    {
        header("$key: $value");
        return new self;
    }

    public function status(int $code)
    {
        http_response_code($code);
        return new self;
    }

    public function make(string $content = '', int $status = 200, array $headers = [])
    {
        http_response_code($status);

        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        echo $content;
    }
}
