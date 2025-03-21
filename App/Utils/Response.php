<?php

namespace App\Utils\Http;

use SimpleXMLElement;

class Response
{
    public function json($data = [])
    {
        $iPreferPretty = filter_var(env('PREFER_PRETTY_PRINT', true), FILTER_VALIDATE_BOOLEAN);
        header('Content-Type: application/json');
        echo json_encode($data, ($iPreferPretty) ? JSON_PRETTY_PRINT : 0);
    }

    public function html($content)
    {
        header('Content-Type: text/html');
        echo $content;
    }

    public function text($content)
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

    public function redirect($url, $code = 302)
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }

    public function download($filePath, $fileName = null)
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

    public function header($key, $value)
    {
        header("$key: $value");
        return new self;
    }

    public function status($code)
    {
        http_response_code($code);
        return new self;
    }

    public function make($content = '', $status = 200, array $headers = [])
    {
        http_response_code($status);

        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        echo $content;
    }
}
