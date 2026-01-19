<?php

namespace App\Foundation\Http;

class HttpHeaders
{
    protected $headers = [];

    public function contentType(string $type)
    {
        $this->headers['Content-Type'] = $type;
        return $this;
    }

    public function acceptEncoding(string $encoding)
    {
        $this->headers['Accept-Encoding'] = $encoding;
        return $this;
    }

    public function cacheControl(string $directives)
    {
        $this->headers['Cache-Control'] = $directives;
        return $this;
    }

    public function authorization(string $token)
    {
        $this->headers['Authorization'] = 'Bearer ' . ltrim($token, 'Bearer ');
        return $this;
    }

    public function contentDisposition(string $type, ?string $filename = null)
    {
        $value = $type;
        if ($filename) {
            $value .= '; filename="' . $filename . '"';
        }
        $this->headers['Content-Disposition'] = $value;
        return $this;
    }

    public function contentLength(int $bytes)
    {
        $this->headers['Content-Length'] = $bytes;
        return $this;
    }
    
    public function contentRange(int $fileSize, ?int $startBytes = null, ?int $endBytes = null)
    {
        $this->headers['Content-Range'] = 'bytes ' . ($startBytes && $endBytes ? $startBytes . '-' . $endBytes : '*') . '/' . $fileSize;
        return $this;
    }

    public function contentDescription(string $description)
    {
        $this->headers['Content-Description'] = $description;
        return $this;
    }

    public function expires(string $date)
    {
        $this->headers['Expires'] = $date;
        return $this;
    }

    public function lastModified(string $date)
    {
        $this->headers['Last-Modified'] = $date;
        return $this;
    }

    public function etag(string $tag)
    {
        $this->headers['ETag'] = $tag;
        return $this;
    }

    public function vary(string $headers)
    {
        $this->headers['Vary'] = $headers;
        return $this;
    }

    public function set(string $name, string $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function merge(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function build()
    {
        return $this->headers;
    }

    public function buildToArray()
    {
        $res = [];
        foreach ($this->headers as $head => $value) {
            $res[] = "$head: $value";
        }
        return $res;
    }
}
